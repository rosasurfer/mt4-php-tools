#!/usr/bin/php
<?php
/**
 * Aktualisiert die lokal vorhandenen Dukascopy-M1-Daten. Bid und Ask werden zu Median gemerged, nach FXT konvertiert und im
 * MyFX-Format gespeichert. Die Dukascopy-Daten sind durchgehend, Feiertage werden, Wochenenden werden nicht gespeichert.
 * Die Daten des aktuellen Tags sind frühestens am nächsten Tag verfügbar.
 *
 *
 * Webseite:      http://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                http://www.dukascopy.com/free/candelabrum/
 *
 * Instrumente:   http://www.dukascopy.com/free/candelabrum/data.json
 *
 * History-Start: http://www.dukascopy.com/datafeed/metadata/HistoryStart.bi5  (Format unbekannt)
 *
 * URL-Format:    Durchgehend eine Datei je Kalendertag ab History-Start,
 *                z.B.: (Januar = 00)
 *                • http://www.dukascopy.com/datafeed/GBPUSD/2013/00/10/BID_candles_min_1.bi5
 *                • http://www.dukascopy.com/datafeed/GBPUSD/2013/11/31/ASK_candles_min_1.bi5
 *
 * Dateiformat:   • Binär, LZMA-gepackt, Zeiten in GMT (keine Sommerzeit).
 *                • In Handelspausen ist durchgehend der letzte Schlußkurs (OHLC) und V=0 (zero) angegeben.
 *
 *                @see class Dukascopy
 *
 *      +------------------------+------------+------------+------------+------------------------+------------------------+
 * FXT: |   Sunday      Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday     Saturday  |   Sunday      Monday   |
 *      +------------------------+------------+------------+------------+------------------------+------------------------+
 *          +------------------------+------------+------------+------------+------------------------+------------------------+
 * GMT:     |   Sunday      Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday     Saturday  |   Sunday      Monday   |
 *          +------------------------+------------+------------+------------+------------------------+------------------------+
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$verbose = 0;                                   // output verbosity

$saveCompressedDukascopyFiles = false;          // ob heruntergeladene Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawDukascopyFiles        = false;          // ob entpackte Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawMyFXData              = true;           // ob unkomprimierte MyFX-Historydaten gespeichert werden sollen


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
   if ($arg == '-h'  )   help() & exit(1);                                          // Hilfe
   if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
   if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
   if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

// Symbole parsen
foreach ($args as $i => $arg) {
   $arg = strToUpper($arg);
   if (!isSet(MyFX::$symbols[$arg]) || MyFX::$symbols[$arg]['provider']!='dukascopy')
      help('unknown or unsupported symbol "'.$args[$i].'"') & exit(1);
   $args[$i] = $arg;
}                                                                                   // ohne Angabe werden alle Dukascopy-Instrumente aktualisiert
$args = $args ? array_unique($args) : array_keys(MyFX::filterSymbols(array('provider'=>'dukascopy')));


// (2) Daten aktualisieren
foreach ($args as $symbol) {
   if (!updateSymbol($symbol))
      exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die M1-Daten eines Symbol.
 *
 * Eine Dukascopy-Datei enthält immer anteilige Daten zweier FXT-Tage. Zum Update eines FXT-Tages sind immer die Daten
 * zweier Dukascopy-Tage notwendig. Die Daten des aktuellen Tags sind frühestens am nächsten Tag verfügbar.
 *
 * @param  string $symbol - Symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateSymbol($symbol) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   $symbol = strToUpper($symbol);

   $startTime  = MyFX::$symbols[$symbol]['historyStart']['M1'];      // Beginns der Dukascopy-Daten dieses Symbols in GMT
   $startTime -= $startTime % DAY;                                   // 00:00 GMT

   global $verbose, $barBuffer;
   $barBuffer        = null;                                         // Barbuffer zurücksetzen
   $barBuffer['bid'] = array();
   $barBuffer['ask'] = array();
   $barBuffer['avg'] = array();

   echoPre('[Info]    '.$symbol);


   // (1) Prüfen, ob sich der Startzeitpunkt der History des Symbols geändert hat
   if (array_search($symbol, array('USDNOK', 'USDSEK', 'USDSGD', 'USDZAR', 'XAUUSD')) === false) {
      $content = downloadData($symbol, $startTime-1*DAY, 'bid', true, false, false);   // Statusmeldungen unterdrücken, nichts speichern
      if (strLen($content)) {
         echoPre('[Notice]  '.$symbol.' M1 history was extended. Please update the history start time.');
         return false;
      }
   }


   // (2) Gesamte Zeitspanne inklusive Wochenenden tageweise durchlaufen, um von vorherigen Durchlaufen ggf. vorhandene
   //     Zwischendateien finden und löschen zu können.
   static $lastMonth=-1;
   $today = ($today=time()) - $today%DAY;                            // 00:00 GMT aktueller Tag

   for ($day=$startTime; $day < $today; $day+=1*DAY) {
      $month = (int) gmDate('m', $day);
      if ($month != $lastMonth) {
         if ($verbose > 0) echoPre('[Info]    '.gmDate('M-Y', $day));
         $lastMonth = $month;
      }
      if (!checkHistory($symbol, $day)) return false;
   }
   echoPre('[Ok]      '.$symbol);

   return true;
}


/**
 * Prüft den Stand der MyFX-History eines einzelnen Forex-Tages und stößt ggf. das Update an.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - GMT-Timestamp des zu prüfenden Tages
 *
 * @return bool - Erfolgsstatus
 */
function checkHistory($symbol, $day) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = gmDate('D, d-M-Y', $day);

   global $verbose, $saveCompressedDukascopyFiles, $saveRawDukascopyFiles, $saveRawMyFXData, $barBuffer;
   $day -= $day%DAY;                                              // 00:00 GMT

   // (1) nur an Wochentagen: prüfen, ob die MyFX-History existiert und ggf. aktualisieren
   if (!MyFX::isForexWeekend($day, 'FXT')) {                      // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
      // History ist ok, wenn entweder die komprimierte MyFX-Datei existiert...
      if (is_file($file=getVar('myfxFile.compressed', $symbol, $day))) {
         if ($verbose > 1) echoPre('[Ok]    '.$shortDate.'   MyFX compressed history file: '.baseName($file));
      }
      // ...oder die unkomprimierte MyFX-Datei gespeichert wird und existiert
      else if ($saveRawMyFXData && is_file($file=getVar('myfxFile.raw', $symbol, $day))) {
         if ($verbose > 1) echoPre('[Ok]    '.$shortDate.'   MyFX raw history file: '.baseName($file));
      }
      // andererseits History aktualisieren
      else if (!updateHistory($symbol, $day)) {                   // da 00:00, kann der GMT- als FXT-Timestamp übergeben werden
         return false;
      }
   }


   // (2) an allen Tagen: nicht mehr benötigte Dateien, Verzeichnisse und Barbuffer-Daten löschen
   $previousDay   = $day - 1*DAY;
   $shortDatePrev = gmDate('D, d-M-Y', $previousDay);

   // Dukascopy-Downloads (gepackt) des Vortages
   if (!$saveCompressedDukascopyFiles) {
      if (is_file($file=getVar('dukaFile.compressed', $symbol, $previousDay, 'bid'))) unlink($file);
      if (is_file($file=getVar('dukaFile.compressed', $symbol, $previousDay, 'ask'))) unlink($file);
   }
   // dekomprimierte Dukascopy-Daten des Vortages
   if (!$saveRawDukascopyFiles) {
      if (is_file($file=getVar('dukaFile.raw', $symbol, $previousDay, 'bid'))) unlink($file);
      if (is_file($file=getVar('dukaFile.raw', $symbol, $previousDay, 'ask'))) unlink($file);
   }

   // lokales Historyverzeichnis des Vortages, wenn Wochenende und es leer ist
   if (MyFX::isForexWeekend($previousDay, 'FXT')) {                  // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
      if (is_dir($dir=getVar('myfxDir', $symbol, $previousDay))) @rmDir($dir);
   }
   // lokales Historyverzeichnis des aktuellen Tages, wenn Wochenende und es leer ist
   if (MyFX::isForexWeekend($day, 'FXT')) {                          // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
      if (is_dir($dir=getVar('myfxDir', $symbol, $day))) @rmDir($dir);
   }
   // Barbuffer-Daten des Vortages
   unset($barBuffer['bid'][$shortDatePrev]);
   unset($barBuffer['ask'][$shortDatePrev]);
   unset($barBuffer['avg'][$shortDatePrev]);

   // Barbuffer-Daten des aktuellen Tages
   unset($barBuffer['bid'][$shortDate]);
   unset($barBuffer['ask'][$shortDate]);
   unset($barBuffer['avg'][$shortDate]);

   return true;
}


/**
 * Aktualisiert die Daten eines einzelnen Forex-Tages. Wird aufgerufen, wenn für einen Wochentag keine lokalen
 * MyFX-Historydateien existieren.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu aktualisierenden Forex-Tages
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory($symbol, $day) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = gmDate('D, d-M-Y', $day);
   global $barBuffer;

   // Bid- und Ask-Daten im Barbuffer suchen und ggf. laden
   $types = array('bid', 'ask');
   foreach ($types as $type) {
      if (!isSet($barBuffer[$type][$shortDate]) || sizeOf($barBuffer[$type][$shortDate])!=1*DAY/MINUTES)
         if (!loadHistory($symbol, $day, $type)) return false;
   }

   // Bid und Ask im Barbuffer mergen
   if (!mergeHistory($symbol, $day)) return false;

   // gemergte Daten speichern
   if (!saveBars($symbol, $day)) return false;

   return true;
}


/**
 * Lädt die Daten eines einzelnen Forex-Tages und Typs in den Barbuffer.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu ladenden Forex-Tages
 * @param  string $type   - Kurstyp
 *
 * @return bool - Erfolgsstatus
 */
function loadHistory($symbol, $day, $type) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = gmDate('D, d-M-Y', $day);
   global $barBuffer, $saveCompressedDukascopyFiles; $barBuffer[$type];

   // Für jeden Forex-Tag werden die GMT-Dukascopy-Daten des vorherigen und des aktuellen Tages benötigt.
   // Die Daten werden jeweils in folgender Reihenfolge gesucht:
   //  • im Barbuffer selbst
   //  • in bereits dekomprimierten Dukascopy-Dateien
   //  • in noch komprimierten Dukascopy-Dateien
   //  • als Dukascopy-Download

   $previousDay = $day - 1*DAY; $previousDayData = false;
   $currentDay  = $day;         $currentDayData  = false;


   // (1) Daten des vorherigen Tages suchen bzw. bereitstellen
   // • im Buffer nachschauen
   if (!$previousDayData && isSet($barBuffer[$type][$shortDate])) {              // Beginnen die Daten im Buffer mit 00:00, liegt
      $previousDayData = ($barBuffer[$type][$shortDate][0]['delta_fxt'] == 0);   // der Teil des vorherigen GMT-Tags dort schon bereit.
   }
   // • dekomprimierte Dukascopy-Datei suchen und verarbeiten
   if (!$previousDayData) {
      if (is_file($file=getVar('dukaFile.raw', $symbol, $previousDay, $type)))
         if (!$previousDayData=processRawDukascopyBarFile($file, $symbol, $previousDay, $type))
            return false;
   }
   // • komprimierte Dukascopy-Datei suchen und verarbeiten
   if (!$previousDayData) {
      if (is_file($file=getVar('dukaFile.compressed', $symbol, $previousDay, $type)))
         if (!$previousDayData=processCompressedDukascopyBarFile($file, $symbol, $previousDay, $type))
            return false;
   }
   // • ggf. Dukascopy-Datei herunterladen und verarbeiten
   if (!$previousDayData) {
      $data = downloadData($symbol, $previousDay, $type, false, $saveCompressedDukascopyFiles);
      if (!$data)                                                                // bei HTTP status 404 (file not found) Abbruch
         return false;
      if (!processCompressedDukascopyBarData($data, $symbol, $previousDay, $type))
         return false;
      $previousDayData = true;
   }


   // (2) Daten des aktuellen Tages suchen bzw.bereitstellen
   // • im Buffer nachschauen
   if (!$currentDayData && isSet($barBuffer[$type][$shortDate])) {               // Enden die Daten im Buffer mit 23:59, liegt
      $size = sizeOf($barBuffer[$type][$shortDate]);                             // der Teil des aktuellen GMT-Tags dort schon bereit.
      $currentDayData = ($barBuffer[$type][$shortDate][$size-1]['delta_fxt'] == 23*HOURS+59*MINUTES);
   }
   // • dekomprimierte Dukascopy-Datei suchen und verarbeiten
   if (!$currentDayData) {
      if (is_file($file=getVar('dukaFile.raw', $symbol, $currentDay, $type)))
         if (!$currentDayData=processRawDukascopyBarFile($file, $symbol, $currentDay, $type))
            return false;
   }
   // • komprimierte Dukascopy-Datei suchen und verarbeiten
   if (!$currentDayData) {
      if (is_file($file=getVar('dukaFile.compressed', $symbol, $currentDay, $type)))
         if (!$currentDayData=processCompressedDukascopyBarFile($file, $symbol, $currentDay, $type))
            return false;
   }
   // • ggf. Dukascopy-Datei herunterladen und verarbeiten
   if (!$currentDayData) {
      static $yesterday; if (!$yesterday) $yesterday=($today=time()) - $today%DAY - 1*DAY;   // 00:00 GMT gestriger Tag
      $saveFile = ($saveCompressedDukascopyFiles || $currentDay==$yesterday);                // beim letzten Durchlauf immer speichern

      $data = downloadData($symbol, $currentDay, $type, false, $saveFile);
      if (!$data)                                                                            // HTTP status 404 (file not found) => Abbruch
         return false;
      if (!processCompressedDukascopyBarData($data, $symbol, $currentDay, $type))
         return false;
      $currentDayData = true;
   }
   return true;
}


/**
 * Merged die Historydaten eines einzelnen Forex-Tages. Wird aufgerufen, wenn Bid- und Ask-Kurse des Tages im Barbuffer liegen.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - FXT-Timestamp des zu mergenden Forex-Tages
 *
 * @return bool - Erfolgsstatus
 */
function mergeHistory($symbol, $day) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = gmDate('D, d-M-Y', $day);
   global $barBuffer;


   // (1) beide Datenreihen nochmal prüfen
   $types = array('bid', 'ask');
   foreach ($types as $type) {
      if (!isSet($barBuffer[$type][$shortDate]) || ($size=sizeOf($barBuffer[$type][$shortDate]))!=1*DAY/MINUTES)
         throw new plRuntimeException('Unexpected number of MyFX '.$type.' bars for '.$shortDate.' in bar buffer: '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');
   }


   // (2) Daten mergen
   foreach ($barBuffer['bid'][$shortDate] as $i => $bid) {
      $ask = $barBuffer['ask'][$shortDate][$i];

      $avg = array();
      $avg['time_fxt' ] =              $bid['time_fxt' ];
      $avg['delta_fxt'] =              $bid['delta_fxt'];
      $avg['open'     ] = (int) round(($bid['open'     ] + $ask['open' ])/2);
      $avg['high'     ] = (int) round(($bid['high'     ] + $ask['high' ])/2);
      $avg['low'      ] = (int) round(($bid['low'      ] + $ask['low'  ])/2);
      $avg['close'    ] = (int) round(($bid['close'    ] + $ask['close'])/2);

      // Resultierende Avg-Bar validieren (Bid- und Ask-Bar für sich allein sind schon validiert).
      // Es kann Spikes mit negativem Spread geben. In diesem Fall werden Open und Close normal berechnet (Average),
      // und High und Low auf das Extrem gesetzt.
      if ($bid['open'] > $ask['open'] || $bid['high'] > $ask['high'] || $bid['low'] > $ask['low'] || $bid['close'] > $ask['close']) {
         $avg['high'] = max($avg['open'], $avg['high'], $avg['low'], $avg['close']);
         $avg['low' ] = min($avg['open'], $avg['high'], $avg['low'], $avg['close']);
      }

      // Ursprünglich wurden die Ticks von Bid- und Ask-Bar einzeln berechnet und diese Werte addiert.
      // Ziel ist jedoch ein möglichst kleiner Tickwert (um Tests nicht unnötig zu verlangsamen).
      // Daher werden die Ticks nur noch von der Avg-Bar berechnet und dieser eine Wert gespeichert.
      $ticks = ($avg['high'] - $avg['low']) << 1;                                            // unchanged bar (O == C)
      if      ($avg['open'] < $avg['close']) $ticks += ($avg['open' ] - $avg['close']);      // bull bar
      else if ($avg['open'] > $avg['close']) $ticks += ($avg['close'] - $avg['open' ]);      // bear bar
      $avg['ticks'] = $ticks ? $ticks : 1;                                                   // Ticks mindestens auf 1 setzen

      $barBuffer['avg'][$shortDate][$i] = $avg;
   }
   return true;
}


/**
 * Lädt eine Dukascopy-M1-Datei und gibt ihren Inhalt zurück.
 *
 * @param  string $symbol    - Symbol der herunterzuladenen Datei
 * @param  int    $day       - Tag der herunterzuladenen Datei
 * @param  string $type      - Kurstyp der herunterzuladenen Datei: 'bid'|'ask'
 * @param  bool   $quiet     - ob Statusmeldungen unterdrückt werden sollen (default: nein)
 * @param  bool   $saveData  - ob die Datei gespeichert werden soll (default: nein)
 * @param  bool   $saveError - ob ein 404-Fehler mit einer entsprechenden Fehlerdatei signalisiert werden soll (default: ja)
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404-Fehler).
 */
function downloadData($symbol, $day, $type, $quiet=false, $saveData=false, $saveError=true) {
   if (!is_int($day))        throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_bool($quiet))     throw new IllegalTypeException('Illegal type of parameter $quiet: '.getType($quiet));
   if (!is_bool($saveData))  throw new IllegalTypeException('Illegal type of parameter $saveData: '.getType($saveData));
   if (!is_bool($saveError)) throw new IllegalTypeException('Illegal type of parameter $saveError: '.getType($saveError));

   $shortDate = gmDate('D, d-M-Y', $day);
   $url       = getVar('dukaUrl', $symbol, $day, $type);
   if (!$quiet) echoPre('[Info]    '.$shortDate.'   url: '.$url);

   // (1) Standard-Browser simulieren
   $userAgent = Config ::get('myfx.useragent'); if (!$userAgent) throw new plInvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');
   $request = HttpRequest ::create()
                          ->setUrl($url)
                          ->setHeader('User-Agent'     , $userAgent                                                       )
                          ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                          ->setHeader('Accept-Language', 'en-us'                                                          )
                          ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                          ->setHeader('Keep-Alive'     , '115'                                                            )
                          ->setHeader('Connection'     , 'keep-alive'                                                     )
                          ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
                          ->setHeader('Referer'        , 'http://www.dukascopy.com/free/candelabrum/'                     );
   $options[CURLOPT_SSL_VERIFYPEER] = false;                         // falls HTTPS verwendet wird

   // (2) HTTP-Request abschicken und auswerten
   $response = CurlHttpClient ::create($options)->send($request);    // TODO: CURL-Fehler wie bei SimpleTrader behandeln
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new plRuntimeException('Unexpected HTTP status '.$status.' ('.HttpResponse::$sc[$status].') for url "'.$url.'"'.NL.printFormatted($response, true));

   // eine leere Antwort ist möglich und wird wie ein 404-Fehler behandelt
   $content = $response->getContent();
   if (!strLen($content))
      $status = 404;


   // (3) Download-Success
   if ($status == 200) {
      // ggf. vorhandene Fehlerdatei löschen
      if (is_file($file=getVar('dukaFile.404', $symbol, $day, $type))) unlink($file);

      // ist das Flag $saveData gesetzt, Content speichern
      if ($saveData) {
         mkDirWritable(getVar('myfxDir', $symbol, $day, $type), 0700);
         $tmpFile = tempNam(dirName($file=getVar('dukaFile.compressed', $symbol, $day, $type)), baseName($file));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $response->getContent());
         fClose($hFile);
         if (is_file($file)) unlink($file);
         rename($tmpFile, $file);                                    // So kann eine existierende Datei niemals korrupt sein.
      }
   }

   // (4) Download-Fehler: ist das Flag $saveError gesetzt, Fehler speichern
   if ($status == 404) {
      if (!$quiet)
         echoPre('[Error]   '.$shortDate.'   url not found (404): '.$url);

      if ($saveError) {
         mkDirWritable(dirName($file=getVar('dukaFile.404', $symbol, $day, $type)), 0700);
         fClose(fOpen($file, 'wb'));
      }
   }
   return ($status==200) ? $response->getContent() : '';
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyBarFile($file, $symbol, $day, $type) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

   global $verbose;
   if ($verbose > 0) echoPre('[Info]    '.gmDate('D, d-M-Y', $day).'   Dukascopy compressed bar file: '.baseName($file));

   return processCompressedDukascopyBarData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyBarData($data, $symbol, $day, $type) {
   if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

   global $saveRawDukascopyFiles;
   $saveAs = $saveRawDukascopyFiles ? getVar('dukaFile.raw', $symbol, $day, $type) : null;

   $rawData = Dukascopy ::decompressHistoryData($data, $saveAs);
   return processRawDukascopyBarData($rawData, $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyBarFile($file, $symbol, $day, $type) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

   global $verbose;
   if ($verbose > 0) echoPre('[Info]    '.gmDate('D, d-M-Y', $day).'   Dukascopy raw bar file: '.baseName($file));

   return processRawDukascopyBarData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyBarData($data, $symbol, $day, $type) {
   if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
   if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_string($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
   $shortDate = gmDate('D, d-M-Y', $day);

   global $barBuffer; $barBuffer[$type];

   // (1) Bars einlesen
   $bars = Dukascopy ::readBarData($data, $symbol, $type, $day);
   $size = sizeOf($bars); if ($size != 1*DAY/MINUTES) throw new plRuntimeException('Unexpected number of Dukascopy bars in '.getVar('dukaName', null, null, $type).': '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');


   // (2) Timestamps und FXT-Daten zu den Bars hinzufügen
   $prev = $next = null;                                             // Die Daten der Datei können einen DST-Wechsel abdecken, wenn
   $fxtOffset = MyFX::fxtTimezoneOffset($day, $prev, $next);         // $day = "Sun, 00:00 GMT" ist. In diesem Fall muß innerhalb
   foreach ($bars as &$bar) {                                        // der Datenreihe bei der Ermittlung von time_fxt und delta_fxt
      $bar['time_gmt' ] = $day + $bar['timeDelta'];                  // auf den nächsten DST-Offset gewechselt werden.
      $bar['delta_gmt'] =        $bar['timeDelta'];
      if ($bar['time_gmt'] >= $next['time'])
         $fxtOffset = $next['offset'];                               // $fxtOffset on-the-fly aktualisieren
      $bar['time_fxt' ] = $bar['time_gmt'] + $fxtOffset;             // Es gilt: FXT = GMT + Offset
      $bar['delta_fxt'] = $bar['time_fxt'] % DAY;                    //     bzw: GMT = FXT - Offset
      unset($bar['timeDelta']);
   }


   // (3) Index von 00:00 FXT bestimmen und Bars FXT-tageweise im Buffer speichern
   $newDayOffset = $size - $fxtOffset/MINUTES;
   if ($fxtOffset == $next['offset']) {                              // bei DST-Change sicherheitshalber Lots prüfen
      $lastBar  = $bars[$newDayOffset-1];
      $firstBar = $bars[$newDayOffset];
      if ($lastBar['lots']/*|| !$firstBar['lots']*/) {
         echoPre('[Warn]    '.$shortDate.'   lots mis-match during DST change.');
         echoPre('Day of DST change ('.gmDate('D, d-M-Y', $lastBar['time_fxt']).') ended with:');
         echoPre($bars[$newDayOffset-1]);
         echoPre('Day after DST change ('.gmDate('D, d-M-Y', $firstBar['time_fxt']).') started with:');
         echoPre($bars[$newDayOffset]);
      }
   }
   $bars1      = array_slice($bars, 0, $newDayOffset);
   $bars2      = array_slice($bars, $newDayOffset);

   $shortDate1 = gmDate('D, d-M-Y', $bars1[0]['time_fxt']-$bars1[0]['delta_fxt']);
   $shortDate2 = gmDate('D, d-M-Y', $bars2[0]['time_fxt']-$bars2[0]['delta_fxt']);

   if (isSet($barBuffer[$type][$shortDate1])) {
      // Sicherstellen, daß die Daten zu mergender Bars nahtlos ineinander übergehen.
      $lastBarTime = $barBuffer[$type][$shortDate1][sizeOf($barBuffer[$type][$shortDate1])-1]['time_fxt'];
      $nextBarTime = $bars1[0]['time_fxt'];
      if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new plRuntimeException('Bar time mis-match, bars to merge: "'.getVar('dukaName', null, null, $type).'", $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
      $barBuffer[$type][$shortDate1] = array_merge($barBuffer[$type][$shortDate1], $bars1);
   }
   else {
      $barBuffer[$type][$shortDate1] = $bars1;
   }

   if (isSet($barBuffer[$type][$shortDate2])) {
      // Sicherstellen, daß die Daten zu mergender Bars nahtlos ineinander übergehen.
      $lastBarTime = $barBuffer[$type][$shortDate2][sizeOf($barBuffer[$type][$shortDate2])-1]['time_fxt'];
      $nextBarTime = $bars2[0]['time_fxt'];
      if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new plRuntimeException('Bar time mis-match, bars to merge: "'.getVar('dukaName', null, null, $type).'", $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
      $barBuffer[$type][$shortDate2] = array_merge($barBuffer[$type][$shortDate2], $bars2);
   }
   else {
      $barBuffer[$type][$shortDate2] = $bars2;
   }

   return true;
}


/**
 * Schreibt die gemergten Bardaten eines FXT-Tages aus dem Barbuffer in die lokale MyFX-Historydatei.
 *
 * @param  string $symbol - Symbol
 * @param  int    $day    - Timestamp des FXT-Tages
 *
 * @return bool - Erfolgsstatus
 */
function saveBars($symbol, $day) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $shortDate = gmDate('D, d-M-Y', $day);
   global $barBuffer, $saveRawMyFXData;


   // (1) gepufferte Datenreihe nochmal prüfen
   $errorMsg = null;
   if (!$errorMsg && !isSet($barBuffer['avg'][$shortDate]))                                    $errorMsg = 'No "avg" bars of '.$shortDate.' in buffer';
   if (!$errorMsg && ($size=sizeOf($barBuffer['avg'][$shortDate]))!=1*DAY/MINUTES)             $errorMsg = 'Invalid number of "avg" bars for '.$shortDate.' in buffer: '.$size;
   if (!$errorMsg && $barBuffer['avg'][$shortDate][0      ]['delta_fxt']!=0                  ) $errorMsg = 'No beginning "avg" bars for '.$shortDate.' in buffer, first bar:'.NL.printFormatted($barBuffer['avg'][$shortDate][0], true);
   if (!$errorMsg && $barBuffer['avg'][$shortDate][$size-1]['delta_fxt']!=23*HOURS+59*MINUTES) $errorMsg = 'No ending "avg" bars for '.$shortDate.' in buffer, last bar:'.NL.printFormatted($barBuffer['avg'][$shortDate][$size-1], true);
   if (!$errorMsg && ($size=sizeOf(array_keys($barBuffer['avg']))) > 1)                        $errorMsg = 'Invalid bar buffer state: found more then one "avg" data series ('.$size.')';
   if ($errorMsg) {
      showBuffer();
      throw new plRuntimeException($errorMsg);
   }


   // (2) Bars in Binärstring umwandeln
   $data = null;
   foreach ($barBuffer['avg'][$shortDate] as $bar) {
      // Bardaten vorm Schreiben validieren
      if ($bar['open' ] > $bar['high'] ||
          $bar['open' ] < $bar['low' ] ||          // aus (H >= O && O >= L) folgt (H >= L)
          $bar['close'] > $bar['high'] ||          // nicht mit min()/max(), da nicht performant
          $bar['close'] < $bar['low' ] ||
         !$bar['ticks']) throw new plRuntimeException('Illegal data for MYFX_BAR of '.gmDate('D, d-M-Y H:i:s', $bar['time_fxt']).": O=$bar[open] H=$bar[high] L=$bar[low] C=$bar[close] V=$bar[ticks]");

      $data .= pack('VVVVVV', $bar['time_fxt'],
                              $bar['open'    ],
                              $bar['high'    ],
                              $bar['low'     ],
                              $bar['close'   ],
                              $bar['ticks'   ]);
   }


   // (3) binäre Daten ggf. unkomprimiert speichern
   if ($saveRawMyFXData) {
      if (is_file($file=getVar('myfxFile.raw', $symbol, $day))) {
         echoPre('[Error]   '.$symbol.' history for '.$shortDate.' already exists');
         return false;
      }
      mkDirWritable(dirName($file));
      $tmpFile = tempNam(dirName($file), baseName($file));
      $hFile   = fOpen($tmpFile, 'wb');
      fWrite($hFile, $data);
      fClose($hFile);
      rename($tmpFile, $file);                                       // So kann eine existierende Datei niemals korrupt sein.
   }


   // (4) binäre Daten ggf. komprimieren und speichern

   return true;
}


/**
 * Verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht ständig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder über viele Funktionsaufrufe hinweg weitergereicht werden müssen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
 *
 * @param  string $id     - eindeutiger Bezeichner der Variable (ID)
 * @param  string $symbol - Symbol oder NULL
 * @param  int    $time   - Timestamp oder NULL
 * @param  string $type   - Kurstyp (bid|ask) oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null, $type=null) {
   //global $varCache;
   static $varCache = array();
   if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time.'|'.$type), $varCache))
      return $varCache[$key];

   if (!is_string($id))                                 throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
   if (!is_null($symbol) && !is_string($symbol))        throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_null($time)) {
      if (!is_int($time))                               throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      if ($time % DAY)                                  throw new plInvalidArgumentException('Invalid parameter $time: '.$time.' (not 00:00)');
   }
   if (!is_null($type)) {
      if (!is_string($type))                            throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
      if ($type!='bid' && $type!='ask')                 throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   }
   $self  = __FUNCTION__;

   if ($id == 'myfxDirDate') {               // $yyyy/$mmL/$dd                                        // lokales Pfad-Datum
      if (!$time)   throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $result = gmDate('Y/m/d', $time);
   }
   else if ($id == 'myfxDir') {              // $dataDirectory/history/dukascopy/$symbol/$dateL       // lokales Verzeichnis
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      static $dataDirectory; if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $dateL         = $self('myfxDirDate', null, $time, null);
      $result        = "$dataDirectory/history/dukascopy/$symbol/$dateL";
   }
   else if ($id == 'myfxFile.raw') {         // $myfxDir/M1.myfx                                      // lokale Datei ungepackt
      $myfxDir = $self('myfxDir' , $symbol, $time, null);
      $result  = "$myfxDir/M1.myfx";
   }
   else if ($id == 'myfxFile.compressed') {  // $myfxDir/M1.rar                                       // lokale Datei gepackt
      $myfxDir = $self('myfxDir' , $symbol, $time, null);
      $result  = "$myfxDir/M1.rar";
   }
   else if ($id == 'dukaName') {             // BID_candles_min_1                                     // Dukascopy-Name
      if (is_null($type)) throw new plInvalidArgumentException('Invalid parameter $type: (null)');
      $result = ($type=='bid' ? 'BID':'ASK').'_candles_min_1';
   }
   else if ($id == 'dukaFile.raw') {         // $myfxDir/$dukaName.bin                                // Dukascopy-Datei ungepackt
      $myfxDir  = $self('myfxDir' , $symbol, $time, null);
      $dukaName = $self('dukaName', null, null, $type);
      $result   = "$myfxDir/$dukaName.bin";
   }
   else if ($id == 'dukaFile.compressed') {  // $myfxDir/$dukaName.bi5                                // Dukascopy-Datei gepackt
      $myfxDir  = $self('myfxDir' , $symbol, $time, null);
      $dukaName = $self('dukaName', null, null, $type);
      $result   = "$myfxDir/$dukaName.bi5";
   }
   else if ($id == 'dukaUrlDate') {          // $yyyy/$mmD/$dd                                        // Dukascopy-URL-Datum
      if (!$time) throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $yyyy   = gmDate('Y', $time);
      $mmD    = strRight(((int)gmDate('m', $time))+99, 2);  // Januar = 00
      $dd     = gmDate('d', $time);
      $result = "$yyyy/$mmD/$dd";
   }
   else if ($id == 'dukaUrl') {  // http://www.dukascopy.com/datafeed/$symbol/$dateD/$dukaName.bi5    // Dukascopy-URL
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      $dateD    = $self('dukaUrlDate', null, $time, null);
      $dukaName = $self('dukaName'   , null, null, $type);
      $result   = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$dukaName.bi5";
   }
   else if ($id == 'dukaFile.404') {         // $myfxDir/$dukaName.404                                // Download-Fehlerdatei (404)
      $myfxDir  = $self('myfxDir' , $symbol, $time, null);
      $dukaName = $self('dukaName', null, null, $type);
      $result   = "$myfxDir/$dukaName.404";
   }
   else {
     throw new plInvalidArgumentException('Unknown parameter $id: "'.$id.'"');
   }

   $varCache[$key] = $result;
   (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

   return $result;
}


/**
 *
 */
function showBuffer() {
   global $barBuffer;

   echoPre(NL);
   foreach ($barBuffer as $type => $days) {
      if (!is_array($days)) {
         echoPre('barBuffer['.$type.'] => '.(is_null($days) ? 'null':$days));
         continue;
      }
      foreach ($days as $day => $bars) {
         if (!is_array($bars)) {
            echoPre('barBuffer['.$type.']['.(is_int($day) ? gmDate('D, d-M-Y', $day):$day).'] => '.(is_null($bars) ? 'null':$bars));
            continue;
         }
         $size = sizeOf($bars);
         $firstBar = $size ? gmDate('H:i', $bars[0      ]['time_fxt']):null;
         $lastBar  = $size ? gmDate('H:i', $bars[$size-1]['time_fxt']):null;
         echoPre('barBuffer['.$type.']['.(is_int($day) ? gmDate('D, d-M-Y', $day):$day).'] => '.str_pad($size, 4, ' ', STR_PAD_LEFT).' bar'.($size==1?'':'s').($firstBar?'  '.$firstBar:'').($size>1?'-'.$lastBar:''));
      }
   }
   echoPre(NL);
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message.NL.NL);

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

 Syntax:  $self [symbol ...]

 Options:  -v    Verbose output.
           -vv   More verbose output.
           -vvv  Very verbose output.
           -h    This help screen.


END;
}
