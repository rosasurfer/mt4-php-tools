#!/usr/bin/php
<?php
use rosasurfer\ministruts\exception\IllegalTypeException;
use rosasurfer\ministruts\exception\InvalidArgumentException;
use rosasurfer\ministruts\exception\RuntimeException;


/**
 * Aktualisiert die lokal vorhandenen Dukascopy-Tickdaten. Die Daten werden nach FXT konvertiert und im MyFX-Format
 * gespeichert. Am Wochenende, an Feiertagen und wenn keine Tickdaten verfügbar sind, sind die Dukascopy-Dateien leer.
 * Wochenenden werden lokal nicht gespeichert. Montags früh können die Daten erst um 01:00 FXT beginnen.
 * Die Daten der aktuellen Stunde sind frühestens ab der nächsten Stunde verfügbar.
 *
 *
 * Webseite:      https://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                https://www.dukascopy.com/free/candelabrum/
 *
 * Instrumente:   https://www.dukascopy.com/free/candelabrum/data.json
 *
 * History-Start: http://datafeed.dukascopy.com/datafeed/metadata/HistoryStart.bi5  (Format unbekannt)
 *
 * URL-Format:    Eine Datei je Tagestunde GMT,
 *                z.B.: (Januar = 00)
 *                • http://datafeed.dukascopy.com/datafeed/EURUSD/2013/00/06/00h_ticks.bi5
 *                • http://datafeed.dukascopy.com/datafeed/EURUSD/2013/05/10/23h_ticks.bi5
 *
 * Dateiformat:   Binär, LZMA-gepackt, Zeiten in GMT (keine Sommerzeit).
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

$saveCompressedDukascopyFiles = false;           // ob heruntergeladene Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawDukascopyFiles        = false;          // ob entpackte Dukascopy-Dateien zwischengespeichert werden sollen
$saveRawMyFXData              = true;           // ob unkomprimierte MyFX-Historydaten gespeichert werden sollen


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
   if ($arg == '-h'  )   exit(1|help());                                            // Hilfe
   if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
   if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
   if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

// Symbole parsen
foreach ($args as $i => $arg) {
   $arg = strToUpper($arg);
   if (!isSet(MyFX::$symbols[$arg]) || MyFX::$symbols[$arg]['provider']!='dukascopy')
      exit(1|help('unknown or unsupported symbol "'.$args[$i].'"'));
   $args[$i] = $arg;
}                                                                                   // ohne Angabe werden alle Dukascopy-Instrumente aktualisiert
$args = $args ? array_unique($args) : array_keys(MyFX::filterSymbols(array('provider'=>'dukascopy')));


// (2) SIGINT-Handler installieren                                                  // Um bei Ctrl-C Destruktoren auszuführen, reicht es,
if (!WINDOWS) pcntl_signal(SIGINT, create_function('$signal', 'exit(0);'));         // wenn im Handler exit() aufgerufen wird.


// (3) Daten aktualisieren
foreach ($args as $symbol) {
   !updateSymbol($symbol) && exit(1);
}
exit(0);


// --- Funktionen ----------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die Tickdaten eines Symbol.
 *
 * Eine Dukascopy-Datei enthält eine Stunde Tickdaten. Die Daten der aktuellen Stunde sind frühestens
 * ab der nächsten Stunde verfügbar.
 *
 * @param  string $symbol - Symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateSymbol($symbol) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   $symbol = strToUpper($symbol);

   echoPre('[Info]    '.$symbol);


   // (1) Beginn des nächsten Forex-Tages ermitteln
   $startTimeGMT = MyFX::$symbols[$symbol]['historyStart']['ticks'];    // Beginn der Tickdaten des Symbols GMT
   $prev = $next = null;
   $fxtOffset = MyFX::fxtTimezoneOffset($startTimeGMT, $prev, $next);   // es gilt: FXT = GMT + Offset
   $startTimeFXT = $startTimeGMT + $fxtOffset;                          // Beginn der Tickdaten FXT

   if ($remainder=$startTimeFXT % DAY) {                                // Beginn auf den nächsten Forex-Tag 00:00 aufrunden, sodaß
      $diff = 1*DAY - $remainder;                                       // wir nur vollständige Forex-Tage verarbeiten. Dabei
      if ($startTimeGMT + $diff >= $next['time']) {                     // berücksichtigen, daß sich zu Beginn des nächsten Forex-Tages
         $startTimeGMT = $next['time'];                                 // der DST-Offset der FXT geändert haben kann.
         $startTimeFXT = $startTimeGMT + $next['offset'];
         if ($remainder=$startTimeFXT % DAY) $diff = 1*DAY - $remainder;
         else                                $diff = 0;
         $fxtOffset = MyFX::fxtTimezoneOffset($startTimeGMT, $prev, $next);
      }
      $startTimeGMT += $diff;                                           // nächster Forex-Tag 00:00 in GMT
      $startTimeFXT += $diff;                                           // nächster Forex-Tag 00:00 in FXT
   }


   // (2) Gesamte Zeitspanne inklusive Wochenenden stundenweise durchlaufen, um von vorherigen Durchlaufen ggf. vorhandene
   // Zwischendateien finden und löschen zu können.
   $thisHour = ($thisHour=time()) - $thisHour%HOUR;                     // Beginn der aktuellen Stunde GMT
   $lastHour = $thisHour - 1*HOUR;                                      // Beginn der letzten Stunde GMT

   for ($gmtHour=$startTimeGMT; $gmtHour < $lastHour; $gmtHour+=1*HOUR) {
      if ($gmtHour >= $next['time'])
         $fxtOffset = MyFX::fxtTimezoneOffset($gmtHour, $prev, $next);  // $fxtOffset on-the-fly aktualisieren
      $fxtHour = $gmtHour + $fxtOffset;

      if (!checkHistory($symbol, $gmtHour, $fxtHour)) return false;
      if (!WINDOWS) pcntl_signal_dispatch();                            // Auf Ctrl-C prüfen, um bei Abbruch die Destruktoren auszuführen.
   }

   echoPre('[Ok]      '.$symbol);
   return true;
}


/**
 * Prüft den Stand der MyFX-Tickdaten einer einzelnen Stunde und stößt ggf. das Update an.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der zu prüfenden Stunde
 * @param  int    $fxtHour - FXT-Timestamp der zu prüfenden Stunde
 *
 * @return bool - Erfolgsstatus
 */
function checkHistory($symbol, $gmtHour, $fxtHour) {
   if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));
   $shortDate = gmDate('D, d-M-Y H:i', $fxtHour);

   global $verbose, $saveCompressedDukascopyFiles, $saveRawDukascopyFiles, $saveRawMyFXData;
   static $lastDay=-1, $lastMonth=-1;

   // (1) nur an Handelstagen prüfen, ob die MyFX-History existiert und ggf. aktualisieren
   if (!MyFX::isForexWeekend($fxtHour, 'FXT')) {
      $day = (int) gmDate('d', $fxtHour);
      if ($day != $lastDay) {
         if ($verbose > 1) echoPre('[Info]    '.gmDate('d-M-Y', $fxtHour));
         else {
            $month = (int) gmDate('m', $fxtHour);
            if ($month != $lastMonth) {
               if ($verbose > 0) echoPre('[Info]    '.gmDate('M-Y', $fxtHour));
               $lastMonth = $month;
            }
         }
         $lastDay = $day;
      }

      // History ist ok, wenn entweder die komprimierte MyFX-Datei existiert...
      if (is_file($file=getVar('myfxFile.compressed', $symbol, $fxtHour))) {
         if ($verbose > 1) echoPre('[Ok]      '.$shortDate.'   MyFX compressed tick file: '.baseName($file));
      }
      // History ist ok, ...oder die unkomprimierte MyFX-Datei gespeichert wird und existiert
      else if ($saveRawMyFXData && is_file($file=getVar('myfxFile.raw', $symbol, $fxtHour))) {
         if ($verbose > 1) echoPre('[Ok]      '.$shortDate.'   MyFX raw tick file: '.baseName($file));
      }
      // andererseits Tickdaten aktualisieren
      else {
         try {
            if (!updateTicks($symbol, $gmtHour, $fxtHour)) return false;
         }
         catch (DukascopyException $ex) {    // bei leerem Response fortfahren (Fehler wurde schon gespeichert)
            if (!strStartsWithI($ex->getMessage(), 'empty response for url:')) throw $ex;
         }
      }
   }


   // (2) an allen Tagen: nicht mehr benötigte Dateien und Verzeichnisse löschen
   // komprimierte Dukascopy-Daten (Downloads) der geprüften Stunde
   if (!$saveCompressedDukascopyFiles) {
      if (is_file($file=getVar('dukaFile.compressed', $symbol, $gmtHour))) unlink($file);
   }
   // dekomprimierte Dukascopy-Daten der geprüften Stunde
   if (!$saveRawDukascopyFiles) {
      if (is_file($file=getVar('dukaFile.raw', $symbol, $gmtHour))) unlink($file);
   }
   // Dukascopy-Downloadverzeichnis der aktuellen Stunde, wenn es leer ist
   if (is_dir($dir=getVar('myfxDir', $symbol, $gmtHour))) @rmDir($dir);
   // lokales Historyverzeichnis der aktuellen Stunde, wenn Wochenende und es leer ist
   if (MyFX::isForexWeekend($fxtHour, 'FXT')) {
      if (is_dir($dir=getVar('myfxDir', $symbol, $fxtHour))) @rmDir($dir);
   }

   return true;
}


/**
 * Aktualisiert die Tickdaten einer einzelnen Forex-Handelstunde. Wird aufgerufen, wenn für diese Stunde keine lokalen
 * MyFX-Tickdateien existieren.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der zu aktualisierenden Stunde
 * @param  int    $fxtHour - FXT-Timestamp der zu aktualisierenden Stunde
 *
 * @return bool - Erfolgsstatus
 */
function updateTicks($symbol, $gmtHour, $fxtHour) {
   if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));
   $shortDate = gmDate('D, d-M-Y H:i', $fxtHour);

   // Tickdaten laden
   $ticks = loadTicks($symbol, $gmtHour, $fxtHour);
   if (!$ticks) return false;

   // Tickdaten speichern
   if (!saveTicks($symbol, $gmtHour, $fxtHour, $ticks)) return false;

   return true;
}


/**
 * Lädt die Daten einer einzelnen Forex-Handelsstunde und gibt sie zurück.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der zu ladenden Stunde
 * @param  int    $fxtHour - FXT-Timestamp der zu ladenden Stunde
 *
 * @return array[] - Array mit Tickdaten
 */
function loadTicks($symbol, $gmtHour, $fxtHour) {
   if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));
   $shortDate = gmDate('D, d-M-Y H:i', $fxtHour);

   // Die Tickdaten der Handelsstunde werden in folgender Reihenfolge gesucht:
   //  • in bereits dekomprimierten Dukascopy-Dateien
   //  • in noch komprimierten Dukascopy-Dateien
   //  • als Dukascopy-Download

   global $saveCompressedDukascopyFiles;
   $ticks = array();

   // dekomprimierte Dukascopy-Datei suchen und bei Erfolg Ticks laden
   if (!$ticks) {
      if (is_file($file=getVar('dukaFile.raw', $symbol, $gmtHour))) {
         $ticks = loadRawDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour);
         if (!$ticks) return false;
      }
   }

   // ggf. komprimierte Dukascopy-Datei suchen und bei Erfolg Ticks laden
   if (!$ticks) {
      if (is_file($file=getVar('dukaFile.compressed', $symbol, $gmtHour))) {
         $ticks = loadCompressedDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour);
         if (!$ticks) return false;
      }
   }

   // ggf. Dukascopy-Datei herunterladen und Ticks laden
   if (!$ticks) {
      $data = downloadTickdata($symbol, $gmtHour, $fxtHour, false, $saveCompressedDukascopyFiles);
      if (!$data) return false;

      $ticks = loadCompressedDukascopyTickData($data, $symbol, $gmtHour, $fxtHour);
      if (!$ticks) return false;
   }

   return $ticks;
}


/**
 * Schreibt die Tickdaten einer Handelsstunde in die lokale MyFX-Tickdatei.
 *
 * @param  string $symbol  - Symbol
 * @param  int    $gmtHour - GMT-Timestamp der Handelsstunde
 * @param  int    $fxtHour - FXT-Timestamp der Handelsstunde
 * @param  array  $ticks   - zu speichernde Ticks
 *
 * @return bool - Erfolgsstatus
 */
function saveTicks($symbol, $gmtHour, $fxtHour, array $ticks) {
   if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));
   $shortDate = gmDate('D, d-M-Y H:i', $fxtHour);
   global $saveRawMyFXData;


   // (1) Tickdaten nochmal prüfen
   if (!$ticks) throw new RuntimeException('No ticks for '.$shortDate);
   $size = sizeof($ticks);
   $fromHour = ($time=$ticks[      0]['time_fxt']) - $time%HOUR;
   $toHour   = ($time=$ticks[$size-1]['time_fxt']) - $time%HOUR;
   if ($fromHour != $fxtHour) throw new RuntimeException('Ticks for '.$shortDate.' do not match the specified hour: $tick[0]=\''.gmDate('d-M-Y H:i:s \F\X\T', $ticks[0]['time_fxt']).'\'');
   if ($fromHour != $toHour)  throw new RuntimeException('Ticks for '.$shortDate.' span multiple hours from=\''.gmDate('d-M-Y H:i:s \F\X\T', $ticks[0]['time_fxt']).'\' to=\''.gmDate('d-M-Y H:i:s \F\X\T', $ticks[$size-1]['time_fxt']).'\'');


   // (2) Ticks binär packen
   $data = null;
   foreach ($ticks as $tick) {
      $data .= pack('VVV', $tick['timeDelta'],
                           $tick['bid'      ],
                           $tick['ask'      ]);
   }


   // (3) binäre Daten ggf. unkomprimiert speichern
   if ($saveRawMyFXData) {
      if (is_file($file=getVar('myfxFile.raw', $symbol, $fxtHour))) {
         echoPre('[Error]   '.$symbol.' ticks for '.$shortDate.' already exists');
         return false;
      }
      mkDirWritable(dirName($file));
      $tmpFile = tempNam(dirName($file), baseName($file));
      $hFile   = fOpen($tmpFile, 'wb');
      fWrite($hFile, $data);
      fClose($hFile);
      rename($tmpFile, $file);            // So kann eine existierende Datei niemals korrupt sein.
   }


   // (4) binäre Daten ggf. komprimieren und speichern

   return true;
}


/**
 * Lädt eine Dukascopy-Tickdatei und gibt ihren Inhalt zurück.
 *
 * @param  string $symbol    - Symbol der herunterzuladenen Datei
 * @param  int    $gmtHour   - GMT-Timestamp der zu ladenden Stunde
 * @param  int    $fxtHour   - FXT-Timestamp der zu ladenden Stunde
 * @param  bool   $quiet     - ob Statusmeldungen unterdrückt werden sollen (default: nein)
 * @param  bool   $saveData  - ob die Datei gespeichert werden soll (default: nein)
 * @param  bool   $saveError - ob ein 404-Fehler mit einer entsprechenden Fehlerdatei signalisiert werden soll (default: ja)
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404-Fehler).
 */
function downloadTickdata($symbol, $gmtHour, $fxtHour, $quiet=false, $saveData=false, $saveError=true) {
   if (!is_int($gmtHour))    throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));
   if (!is_int($fxtHour))    throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));
   if (!is_bool($quiet))     throw new IllegalTypeException('Illegal type of parameter $quiet: '.getType($quiet));
   if (!is_bool($saveData))  throw new IllegalTypeException('Illegal type of parameter $saveData: '.getType($saveData));
   if (!is_bool($saveError)) throw new IllegalTypeException('Illegal type of parameter $saveError: '.getType($saveError));
   global$verbose;

   $shortDate = gmDate('D, d-M-Y H:i', $fxtHour);
   $url       = getVar('dukaUrl', $symbol, $gmtHour);
   if (!$quiet && $verbose > 1) echoPre('[Info]    '.$shortDate.'   url: '.$url);


   // (1) Standard-Browser simulieren
   $userAgent = Config::get('myfx.useragent'); if (!$userAgent) throw new InvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');
   $request = HttpRequest::create()
                         ->setUrl($url)
                         ->setHeader('User-Agent'     , $userAgent                                                       )
                         ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                         ->setHeader('Accept-Language', 'en-us'                                                          )
                         ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                         ->setHeader('Connection'     , 'keep-alive'                                                     )
                         ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
                         ->setHeader('Referer'        , 'http://www.dukascopy.com/free/candelabrum/'                     );
   $options[CURLOPT_SSL_VERIFYPEER] = false;                            // falls HTTPS verwendet wird
   //$options[CURLOPT_VERBOSE     ] = true;

   // (2) HTTP-Request abschicken und auswerten
   static $httpClient = null;
   !$httpClient && $httpClient=CurlHttpClient::create($options);        // Instanz für KeepAlive-Connections wiederverwenden

   $response = $httpClient->send($request);                             // TODO: CURL-Fehler wie bei SimpleTrader behandeln
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new RuntimeException('Unexpected HTTP status '.$status.' ('.HttpResponse::$sc[$status].') for url "'.$url.'"'.NL.printPretty($response, true));

   // eine leere Antwort ist möglich und wird als Fehler behandelt
   $content = $response->getContent();
   if ($status == 404) $content = '';                                   // möglichen Content eines 404-Fehlers zurücksetzen


   // (3) Download-Success: 200 und Datei ist nicht leer
   if ($status==200 && strLen($content)) {
      // vorhandene Fehlerdateien löschen (haben FXT-Namen)
      if (is_file($file=getVar('dukaFile.404',   $symbol, $fxtHour))) unlink($file);
      if (is_file($file=getVar('dukaFile.empty', $symbol, $fxtHour))) unlink($file);

      // ist das Flag $saveData gesetzt, Content speichern
      if ($saveData) {
         mkDirWritable(getVar('myfxDir', $symbol, $gmtHour), 0700);
         $tmpFile = tempNam(dirName($file=getVar('dukaFile.compressed', $symbol, $gmtHour)), baseName($file));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $content);
         fClose($hFile);
         if (is_file($file)) unlink($file);
         rename($tmpFile, $file);                                       // So kann eine existierende Datei niemals korrupt sein.
      }
   }

   // (4) Download-Fehler: ist das Flag $saveError gesetzt, Fehler speichern
   else {
      if ($saveError) {                                                 // Fehlerdatei unter FXT-Namen speichern
         $file = getVar($status==404 ? 'dukaFile.404':'dukaFile.empty', $symbol, $fxtHour);
         mkDirWritable(dirName($file), 0700);
         fClose(fOpen($file, 'wb'));
      }

      if (!$quiet) {
         if ($status==404) echoPre('[Error]   '.$shortDate.'   url not found (404): '.$url);
         else              echoPre('[Warn]    '.$shortDate.'   empty response: '.$url);
      }

      // bei leerem Response Exception werfen, damit eine Schleife ggf. fortgesetzt werden kann
      if ($status != 404) throw new DukascopyException('empty response for url: '.$url);
   }
   return $content;
}


/**
 * Lädt die in einem komprimierten Dukascopy-Tickfile enthaltenen Ticks.
 *
 * @return array[] - Array mit Tickdaten
 */
function loadCompressedDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));

   global $verbose;
   if ($verbose > 0) echoPre('[Info]    '.gmDate('D, d-M-Y H:i', $fxtHour).'   Dukascopy compressed tick file: '.baseName($file));

   return loadCompressedDukascopyTickData(file_get_contents($file), $symbol, $gmtHour, $fxtHour);
}


/**
 * Lädt die in einem komprimierten String enthaltenen Dukascopy-Tickdaten.
 *
 * @return array[] - Array mit Tickdaten
 */
function loadCompressedDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {
   if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));

   global $saveRawDukascopyFiles;
   $saveAs = $saveRawDukascopyFiles ? getVar('dukaFile.raw', $symbol, $gmtHour) : null;

   $rawData = Dukascopy ::decompressHistoryData($data, $saveAs);
   return loadRawDukascopyTickData($rawData, $symbol, $gmtHour, $fxtHour);
}


/**
 * Lädt die in einem unkomprimierten Dukascopy-Tickfile enthaltenen Ticks.
 *
 * @return array[] - Array mit Tickdaten
 */
function loadRawDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));

   global $verbose;
   if ($verbose > 0) echoPre('[Info]    '.gmDate('D, d-M-Y H:i', $fxtHour).'   Dukascopy raw tick file: '.baseName($file));

   return loadRawDukascopyTickData(file_get_contents($file), $symbol, $gmtHour, $fxtHour);
}


/**
 * Lädt die in einem unkomprimierten String enthaltenen Dukascopy-Tickdaten.
 *
 * @return array[] - Array mit Tickdaten
 */
function loadRawDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {
   if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
   if (!is_int($gmtHour)) throw new IllegalTypeException('Illegal type of parameter $gmtHour: '.getType($gmtHour));
   if (!is_int($fxtHour)) throw new IllegalTypeException('Illegal type of parameter $fxtHour: '.getType($fxtHour));

   // Ticks einlesen
   $ticks = Dukascopy::readTickData($data);

   // GMT- und FXT-Timestamps hinzufügen
   foreach ($ticks as &$tick) {
      $sec    = (int)($tick['timeDelta'] / 1000);
      $millis =       $tick['timeDelta'] % 1000;

      $tick['time_gmt']    = $gmtHour + $sec;
      $tick['time_fxt']    = $fxtHour + $sec;
      $tick['time_millis'] = $millis;
   }
   return $ticks;
}


/**
 * Erzeugt und verwaltet dynamisch generierte Variablen.
 *
 * Evaluiert und cacht ständig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht global gespeichert oder über viele Funktionsaufrufe hinweg weitergereicht werden müssen,
 * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
 *
 * @param  string $id     - eindeutiger Bezeichner der Variable (ID)
 * @param  string $symbol - Symbol oder NULL
 * @param  int    $time   - Timestamp oder NULL
 *
 * @return string - Variable
 */
function getVar($id, $symbol=null, $time=null) {
   //global $varCache;
   static $varCache = array();
   if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
      return $varCache[$key];

   if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
   if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

   static $dataDirectory;
   $self = __FUNCTION__;

   if ($id == 'myfxDirDate') {               // $yyyy/$mmL/$dd                                                 // lokales Pfad-Datum
      if (!$time)   throw new InvalidArgumentException('Invalid parameter $time: '.$time);
      $result = gmDate('Y/m/d', $time);
   }
   else if ($id == 'myfxDir') {              // $dataDirectory/history/myfx/$type/$symbol/$myfxDirDate         // lokales Verzeichnis
      if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $type          = MyFX::$symbols[$symbol]['type'];
      $myfxDirDate   = $self('myfxDirDate', null, $time);
      $result        = "$dataDirectory/history/myfx/$type/$symbol/$myfxDirDate";
   }
   else if ($id == 'myfxFile.raw') {         // $myfxDir/${hour}h_ticks.myfx                                   // lokale Datei ungepackt
      $myfxDir = $self('myfxDir', $symbol, $time);
      $hour    = gmDate('H', $time);
      $result  = "$myfxDir/${hour}h_ticks.myfx";
   }
   else if ($id == 'myfxFile.compressed') {  // $myfxDir/${hour}h_ticks.rar                                    // lokale Datei gepackt
      $myfxDir = $self('myfxDir', $symbol, $time);
      $hour    = gmDate('H', $time);
      $result  = "$myfxDir/${hour}h_ticks.rar";
   }
   else if ($id == 'dukaFile.raw') {         // $myfxDir/${hour}h_ticks.bin                                    // Dukascopy-Datei ungepackt
      $myfxDir  = $self('myfxDir', $symbol, $time);
      $hour    = gmDate('H', $time);
      $result   = "$myfxDir/${hour}h_ticks.bin";
   }
   else if ($id == 'dukaFile.compressed') {  // $myfxDir/${hour}h_ticks.bi5                                    // Dukascopy-Datei gepackt
      $myfxDir = $self('myfxDir', $symbol, $time);
      $hour    = gmDate('H', $time);
      $result  = "$myfxDir/${hour}h_ticks.bi5";
   }
   else if ($id == 'dukaUrlDate') {          // $yyyy/$mmD/$dd                                                 // Dukascopy-URL-Datum
      if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
      $yyyy   = gmDate('Y', $time);
      $mmD    = strRight(((int)gmDate('m', $time))+99, 2);  // Januar = 00
      $dd     = gmDate('d', $time);
      $result = "$yyyy/$mmD/$dd";
   }
   else if ($id == 'dukaUrl') {              // http://datafeed.dukascopy.com/datafeed/$symbol/$dukaUrlDate/${hour}h_ticks.bi5  // URL
      if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      $dukaUrlDate = $self('dukaUrlDate', null, $time);
      $hour        = gmDate('H', $time);
      $result      = "http://datafeed.dukascopy.com/datafeed/$symbol/$dukaUrlDate/${hour}h_ticks.bi5";
   }
   else if ($id == 'dukaFile.404') {         // $myfxDir/${hour}h_ticks.404                                    // Download-Fehler 404
      $myfxDir = $self('myfxDir', $symbol, $time);
      $hour    = gmDate('H', $time);
      $result  = "$myfxDir/${hour}h_ticks.404";
   }
   else if ($id == 'dukaFile.empty') {       // $myfxDir/${hour}h_ticks.na                                     // Download-Fehler leerer Response
      $myfxDir = $self('myfxDir', $symbol, $time);
      $hour    = gmDate('H', $time);
      $result  = "$myfxDir/${hour}h_ticks.na";
   }
   else {
     throw new InvalidArgumentException('Unknown parameter $id: "'.$id.'"');
   }

   $varCache[$key] = $result;
   (sizeof($varCache) > ($maxSize=128)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

   return $result;
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
