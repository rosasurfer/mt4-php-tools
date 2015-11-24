#!/usr/bin/php
<?php
/**
 * Aktualisiert die vorhandenen Dukascopy-M1-Daten. Fehlende Daten werden heruntergeladen, nach FXT konvertiert und in einem
 * eigenen komprimierten Format gespeichert. Die Dukascopy-Daten sind durchgehend, lokal werden jedoch für Wochenenden und
 * Feiertage (1. Januar und 25. Dezember) keine Daten gespeichert.
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
 *                • Die Daten des aktuellen Tags sind frühestens am nächsten Tag verfügbar.
 *
 *                @see class Dukascopy
 *
 *      +------------------------+------------+------------+------------+------------------------+------------------------+
 * FXT: |   Sunday      Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday     Saturday  |   Sunday      Monday   |
 *      +------------------------+------------+------------+------------+------------------------+------------------------+
 *          +------------------------+------------+------------+------------+------------------------+------------------------+
 * GMT:     |   Sunday      Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday     Saturday  |   Sunday      Monday   |
 *          +------------------------+------------+------------+------------+------------------------+------------------------+
 *
 * TODO: Update im aktuellen Verzeichnis mit Combi-Lock synchronisieren, damit parallel laufende Scripte sich vertragen.
 *
 *       @see http://stackoverflow.com/questions/5449395/file-locking-in-php
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// -- Konfiguration --------------------------------------------------------------------------------------------------------------------------------


$keepCompressedDukascopyFiles = false;          // ob heruntergeladene Dukascopy-Dateien behalten werden sollen
$keepRawDukascopyFiles        = false;          // ob entpackte Dukascopy-Dateien behalten werden sollen


// History-Start der einzelnen Instrumente bei Dukascopy (geprüft am 21.06.2013)
$startTimes = array(//'AUDCAD' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'AUDCHF' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'AUDJPY' => strToTime('2003-11-30 00:00:00 GMT'),
                    //'AUDNZD' => strToTime('2006-12-08 00:00:00 GMT'),
                      'AUDUSD' => strToTime('2003-08-03 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'CADCHF' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'CADJPY' => strToTime('2004-10-20 00:00:00 GMT'),
                    //'CHFJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURAUD' => strToTime('2005-10-02 00:00:00 GMT'),
                    //'EURCAD' => strToTime('2004-10-20 00:00:00 GMT'),
                    //'EURCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURGBP' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'EURNOK' => strToTime('2004-10-20 00:00:00 GMT'),
                    //'EURNZD' => strToTime('2005-12-26 00:00:00 GMT'),
                    //'EURSEK' => strToTime('2004-10-27 00:00:00 GMT'),
                      'EURUSD' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'GBPAUD' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'GBPCAD' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'GBPCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'GBPJPY' => strToTime('2003-08-03 00:00:00 GMT'),
                    //'GBPNZD' => strToTime('2006-01-01 00:00:00 GMT'),
                      'GBPUSD' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'NZDCAD' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'NZDCHF' => strToTime('2006-01-01 00:00:00 GMT'),
                    //'NZDJPY' => strToTime('2006-01-01 00:00:00 GMT'),
                      'NZDUSD' => strToTime('2003-08-03 00:00:00 GMT'),    // geprüft am 18.11.2015
                      'USDCAD' => strToTime('2003-08-03 00:00:00 GMT'),    // geprüft am 18.11.2015
                      'USDCHF' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                      'USDJPY' => strToTime('2003-05-04 00:00:00 GMT'),    // geprüft am 18.11.2015
                    //'USDNOK' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'USDSEK' => strToTime('2003-08-08 00:00:00 GMT'),
                    //'USDSGD' => strToTime('2004-11-16 00:00:00 GMT'),
                    //'XAGUSD' => strToTime('1997-08-13 00:00:00 GMT'),
                    //'XAUUSD' => strToTime('1999-09-01 00:00:00 GMT'),
);


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
if (!$args) help() & exit(1);

foreach ($args as $i => $arg) {
   if ($arg=="'*'" || $arg=='"*"')
      $args[$i] = $arg = '*';
   if ($arg != '*') {
      $arg = strToUpper($arg);
      if (!isSet($startTimes[$arg])) help('error: unknown symbol "'.$args[$i].'"') & exit(1);
      $args[$i] = $arg;
   }
}
$args = in_array('*', $args) ? array_keys($startTimes) : array_unique($args);    // '*' wird durch alle Symbole ersetzt


// (2) Buffer zum Zwischenspeichern geladener Bardaten
$barBuffer = array();
$varCache  = array();


// (3) Daten aktualisieren
foreach ($args as $symbol) {
   if (!updateSymbol($symbol, $startTimes[$symbol]))
      exit(1);
}
exit(0);


// -- Ende -----------------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die M1-Daten eines Symbol.
 *
 * @param string $symbol    - Symbol
 * @param int    $startTime - Beginn der Dukascopy-Daten dieses Symbols
 *
 * @return bool - Erfolgsstatus
 */
function updateSymbol($symbol, $startTime) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   $symbol = strToUpper($symbol);
   if (!is_int($startTime)) throw new IllegalTypeException('Illegal type of parameter $startTime: '.getType($startTime));
   $startTime -= $startTime % DAY;                                   // 00:00 GMT

   global $barBuffer;
   $barBuffer        = null;                                         // Barbuffer zurücksetzen
   $barBuffer['bid'] = array();
   $barBuffer['ask'] = array();


   // (1) Prüfen, ob sich der Startzeitpunkt des Symbols geändert hat
   /*
   $content = downloadData($symbol, $startTime-1*DAY, 'bid', true, false, false);   // Statusmeldungen unterdrücken, nichts speichern
   if (strLen($content)) {
      echoPre('[Notice]  '.$symbol.' history was extended. Please update the history start time.');
      return false;
   }
   */


   // (2) Eine Dukascopy-Datei enthält immer anteilige Daten zweier FXT-Tage. Zum Update eines FXT-Tages sind immer
   //     zwei Dukascopy-Dateien notwendig. Die Daten des aktuellen Tags sind frühestens am nächsten Tag verfügbar.
   //
   // Gesamte Zeitspanne inklusive Wochenenden und Feiertagen tageweise durchlaufen, um von vorherigen Durchlaufen
   // ggf. vorhandene Zwischendateien finden und löschen zu können.
   $today = ($today=time()) - $today%DAY;                            // 00:00 GMT aktueller Tag

   for ($day=$startTime; $day < $today; $day+=1*DAY) {
      if (!checkHistory($symbol, $day, 'bid')) return false;
      //if (!checkHistory($symbol, $day, 'ask')) return false;
   }
   return true;
}


/**
 * Prüft den Stand der MyFX-History eines einzelnen Tages und Typs und stößt ggf. das Update an.
 *
 * @param string $symbol - Symbol
 * @param int    $day    - Timestamp des zu prüfenden Tages
 * @param string $type   - Kurstyp: 'bid'|'ask'
 *
 * @return bool - Erfolgsstatus
 */
function checkHistory($symbol, $day, $type) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $day      -= $day % DAY;                                          // 00:00 GMT
   $shortDate = date('D, d-M-Y', $day);                              // Fri, 11-Jul-2003

   // (1) nur an Handelstagen: prüfen, ob die lokale MyFX-History existiert
   if (MyFX::isTradingDay($day)) {                                   // um 00:00 GMT sind GMT- und FXT-Wochentag immer gleich
      // History ist ok, wenn die lokale RAR- oder .bin-Datei existieren
      if (is_file($file=value('myfxFile.rar', $symbol, $day, $type))) {
         echoPre('[Ok]    '.$shortDate.'   MyFX compressed history file: '.baseName($file));
      }
      else if (is_file($file=value('myfxFile.bin', $symbol, $day, $type))) {
         echoPre('[Ok]    '.$shortDate.'   MyFX raw history file: '.baseName($file));
      }
      else {
         // History des Tages aktualisieren
         if (!updateHistory($symbol, $day, $type))
            return false;
      }
   }


   // (2) an allen Tagen: ggf. zu löschende Daten, Dateien und Verzeichnisse zum Löschen vormerken
   global $keepCompressedDukascopyFiles, $keepRawDukascopyFiles;
   static $filesToDelete = array();

   // Dukascopy-Download (gepackt)
   if (!$keepCompressedDukascopyFiles && is_file($file=value('dukaFile.lzma', $symbol, $day, $type))) {
      $filesToDelete[] = $file;
   }
   // Dukascopy-Download (entpackt)
   if (!$keepRawDukascopyFiles && is_file($file=value('dukaFile.bin', $symbol, $day, $type))) {
      $filesToDelete[] = $file;
   }
   // Download-Fehlerdatei (404)
   //if ($isUpToDate && is_file($file=value('dukaFile.404', $symbol, $day, $type))) {
   //   $filesToDelete[] = $file;
   //}
   // lokales Historyverzeichnis eines Wochenendes oder Feiertags (wird nur gelöscht, wenn es leer ist)
   if (!MyFX::isTradingDay($day) && is_dir($dir=value('myfxDir', $symbol, $day))) {
      $filesToDelete[] = $dir;
   }

   // TODO: Dateien und Verzeichnisse des vorherigen Tages löschen (wurden auch zum Update benötigt)
   // TODO: Daten im BarBuffer löschen

   static $counter = 0; $counter++;
   if ($counter >= 15) {
      showBuffer();
      return false;
   }
   return true;
}


/**
 * Aktualisiert die Daten eines einzelnen FXT-Tages und Typs (Bid oder Ask). Wird aufgerufen, wenn für einen Handelstag weder
 * eine gepackte noch eine ungepackte MyFX-Historydatei existieren.
 *
 * @param string $symbol - Symbol
 * @param int    $day    - Timestamp des zu aktualisierenden FXT-Tages
 * @param string $type   - Kurstyp: 'bid'|'ask'
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory($symbol, $day, $type) {
   if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   $day      -= $day % DAY;                                          // 00:00
   $shortDate = date('D, d-M-Y', $day);                              // Fri, 11-Jul-2003
   global $barBuffer;

   // Für jeden FXT-Tag werden die GMT-Dukascopy-Daten des vorherigen und des aktuellen Tages benötigt.
   // Die Daten werden jeweils in folgender Reihenfolge gesucht:
   //  • im Barbuffer (Schlüssel: string $shortDate)
   //  • in dekomprimierten Dukascopy-Dateien
   //  • in noch komprimierten Dukascopy-Dateien
   //  • als Dukascopy-Download


   // (1) Daten des vorherigen Tages suchen bzw. bereitstellen
   $previousDay     = $day - 1*DAY;
   $previousDayData = false;

   // • im Buffer nachschauen
   if (!$previousDayData && isSet($barBuffer[$type][$shortDate])) {              // Beginnen die Daten im Buffer mit 00:00 FXT,
      $previousDayData = ($barBuffer[$type][$shortDate][0]['delta_fxt'] == 0);   // liegt der Teil des vorherigen GMT-Tags dort bereit.
   }
   // • dekomprimierte Dukascopy-Datei suchen und ggf. verarbeiten
   if (!$previousDayData) {
      if (is_file($file=value('dukaFile.bin', $symbol, $previousDay, $type)))
         if (!$previousDayData=processRawDukascopyFile($file, $symbol, $previousDay, $type))
            return false;
   }
   // • komprimierte Dukascopy-Datei suchen und ggf. verarbeiten
   if (!$previousDayData) {
      if (is_file($file=value('dukaFile.lzma', $symbol, $previousDay, $type)))
         if (!$previousDayData=processCompressedDukascopyFile($file, $symbol, $previousDay, $type))
            return false;
   }
   // • ggf. Dukascopy-Datei herunterladen und verarbeiten
   if (!$previousDayData) {
      if (!processDownload($symbol, $previousDay, $type))                        // HTTP status 404 (file not found)
         return true;                                                            // => diesen Datensatz abbrechen und fortsetzen
   }
   // • Buffer nochmal prüfen
   if (!isSet($barBuffer[$type][$shortDate]) || $barBuffer[$type][$shortDate][0]['delta_fxt']!=0) {
      showBuffer();
      throw new plRuntimeException('No beginning bars of '.$shortDate.' found in buffer');
   }


   // (2) Daten des aktuellen Tages suchen bzw. bereitstellen
   $currentDay     = $day;
   $currentDayData = false;

   // • im Buffer nachschauen
   if (!$currentDayData && isSet($barBuffer[$type][$shortDate])) {               // Enden die Daten im Buffer mit 23:59 FXT,
      $size = sizeOf($barBuffer[$type][$shortDate]);                             // liegt der Teil des aktuellen GMT-Tags dort bereit.
      $currentDayData = ($barBuffer[$type][$shortDate][$size-1]['delta_fxt'] == 23*HOURS+59*MINUTES);
   }
   // • dekomprimierte Dukascopy-Datei suchen und ggf. verarbeiten
   if (!$currentDayData) {
      if (is_file($file=value('dukaFile.bin', $symbol, $currentDay, $type)))
         if (!$currentDayData=processRawDukascopyFile($file, $symbol, $currentDay, $type))
            return false;
   }
   // • komprimierte Dukascopy-Datei suchen und ggf. verarbeiten
   if (!$currentDayData) {
      if (is_file($file=value('dukaFile.lzma', $symbol, $currentDay, $type)))
         if (!$currentDayData=processCompressedDukascopyFile($file, $symbol, $currentDay, $type))
            return false;
   }
   // • ggf. Dukascopy-Datei herunterladen und verarbeiten
   if (!$currentDayData) {
      if (!processDownload($symbol, $currentDay, $type))                         // HTTP status 404 (file not found)
         return true;                                                            // => diesen Datensatz abbrechen und fortsetzen
   }
   // • Buffer nochmal prüfen
   if (!isSet($barBuffer[$type][$shortDate]) || $barBuffer[$type][$shortDate][sizeOf($barBuffer[$type][$shortDate])-1]['delta_fxt']!=23*HOURS+59*MINUTES) {
      showBuffer();
      throw new plRuntimeException('No ending bars of '.$shortDate.' found in buffer');
   }


   // (3) alle Daten sind vollständig und liegen im Buffer bereit



   /*
   // falls Fehlerdatei existiert
   else if (is_file($file404)) {
      echoPre('[Info]  '.$shortDate.'   Skipping '.$symbol.' (404 status file found)');
   }
   */
   return true;
}


/**
 * Lädt eine Dukascopy-Datei und gibt ihren Inhalt zurück.
 *
 * @param string $symbol    - Symbol der herunterzuladenen Datei
 * @param int    $day       - Tag der herunterzuladenen Datei
 * @param string $type      - Kurstyp der herunterzuladenen Datei: 'bid'|'ask'
 * @param bool   $quiet     - ob Statusmeldungen unterdrückt werden sollen (default: nein)
 * @param bool   $saveData  - ob die Daten zusätzlich gespeichert werden sollen (default: nein)
 * @param bool   $saveError - ob ein 404-Fehler in einer entsprechenden Datei gespeichert werden soll (default: ja)
 *
 * @return string - Content der heruntergeladenen Datei oder Leerstring, wenn die Resource nicht gefunden wurde (404).
 */
function downloadData($symbol, $day, $type, $quiet=false, $saveData=false, $saveError=true) {
   if (!is_int($day))        throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_bool($quiet))     throw new IllegalTypeException('Illegal type of parameter $quiet: '.getType($quiet));
   if (!is_bool($saveData))  throw new IllegalTypeException('Illegal type of parameter $saveData: '.getType($saveData));
   if (!is_bool($saveError)) throw new IllegalTypeException('Illegal type of parameter $saveError: '.getType($saveError));
   $day      -= $day % DAY;                                          // 00:00
   $shortDate = date('D, d-M-Y', $day);                              // Fri, 11-Jul-2003
   $url       = value('dukaUrl', $symbol, $day, $type);
   if (!$quiet) {
      echoPre('[Info]  '.$shortDate.'   url: '.$url);
   }

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

   // (3) Download-Success
   if ($status == 200) {
      // ggf. vorhandene Fehlerdatei löschen
      if (is_file($file=value('dukaFile.404', $symbol, $day, $type))) unlink($file);

      // ist das Flag $saveData gesetzt, Content speichern
      if ($saveData) {
         mkDirWritable(value('myfxDir', $symbol, $day, $type), 0700);
         $tmpFile = tempNam(dirName($file=value('dukaFile.lzma', $symbol, $day, $type)), baseName($file));
         $hFile   = fOpen($tmpFile, 'wb');
         fWrite($hFile, $response->getContent());
         fClose($hFile);
         if (is_file($file)) unlink($file);                          // So kann eine existierende Datei niemals korrupt sein.
         rename($tmpFile, $file);
      }
   }

   // (4) Download-Fehler: ist das Flag $saveError gesetzt, Fehler speichern
   if ($status == 404) {
      if (!$quiet) {
         echoPre('[Error] '.$shortDate.'   url not found (404): '.$url);
      }
      if ($saveError) {
         mkDirWritable(dirName($file=value('dukaFile.404', $symbol, $day, $type)), 0700);
         fClose(fOpen($file, 'wb'));
      }
   }
   return ($status==200) ? $response->getContent() : '';
}


/**
 * @return bool - Erfolgsstatus
 */
function processDownload($symbol, $day, $type) {
   global $keepCompressedDukascopyFiles;

   $data = downloadData($symbol, $day, $type, false, $keepCompressedDukascopyFiles);
   if (!$data)                                                       // HTTP status 404 (file not found)
      return false;
   if (!processCompressedDukascopyData($data, $symbol, $day, $type))
      return false;
   return true;
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyFile($file, $symbol, $day, $type) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

   $shortDate = date('D, d-M-Y', $day);
   echoPre('[Info]  '.$shortDate.'   Dukascopy compressed file: '.baseName($file));
   return processCompressedDukascopyData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processCompressedDukascopyData($data, $symbol, $day, $type) {
   if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

   global $keepRawDukascopyFiles;
   $saveAs = $keepRawDukascopyFiles ? value('dukaFile.bin', $symbol, $day, $type) : null;

   $rawData = Dukascopy ::decompressBarData($data, $saveAs);
   return processRawDukascopyData($rawData, $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyFile($file, $symbol, $day, $type) {
   if (!is_string($file)) throw new IllegalTypeException('Illegal type of parameter $file: '.getType($file));
   if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

   $shortDate = date('D, d-M-Y', $day);
   echoPre('[Info]  '.$shortDate.'   Dukascopy raw history file: '.baseName($file));
   return processRawDukascopyData(file_get_contents($file), $symbol, $day, $type);
}


/**
 * @return bool - Erfolgsstatus
 */
function processRawDukascopyData($data, $symbol, $day, $type) {
   if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
   if (!is_int($day))     throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));
   if (!is_string($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
   global $barBuffer; $barBuffer[$type];
   $day -= $day % DAY;                                               // 00:00

   // (1) Bars einlesen
   $bars = Dukascopy ::readBars($data);
   $size = sizeOf($bars); if ($size != 1*DAY/MINUTES) throw new plRuntimeException('Unexpected number of Dukascopy bars in '.value('dukaName', null, null, $type).': '.$size.' ('.($size > 1*DAY/MINUTES ? 'more':'less').' then a day)');


   // (2) Timestamps und FXT-Daten hinzufügen
   $dstChange = false;
   $prev = $next = null;                                             // Die Daten der Datei können einen DST-Wechsel abdecken, wenn
   $fxtOffset = MyFX ::getGmtToFxtTimeOffset($day, $prev, $next);    // $day = "Sun, 00:00 GMT" ist. In diesem Fall muß innerhalb
   foreach ($bars as $i => &$bar) {                                  // der Datenreihe auf den nächsten DST-Offset gewechselt werden.
      $bar['time_gmt' ] = $day + $bar['timeDelta'];
      $bar['delta_gmt'] =        $bar['timeDelta'];
      if ($bar['time_gmt'] >= $next['time']) {
         if ($fxtOffset != $next['offset']) {
            echoPre(NL.'DST change'.NL.NL);
            $dstChange = true;
         }
         $fxtOffset = $next['offset'];                               // $fxtOffset on-the-fly aktualisieren
      }
      $bar['time_fxt' ] = $bar['time_gmt'] - $fxtOffset;
      $bar['delta_fxt'] = $bar['time_fxt'] % DAY;
      unset($bar['timeDelta']);
   }
   if ($dstChange) {
      $newDayOffset = $size + $fxtOffset/MINUTES;
      echoPre('previous day ended with:');
      echoPre($bars[$newDayOffset-1]);
      echoPre('current day starts with:');
      echoPre($bars[$newDayOffset]);
   }


   // (3) Index von 00:00 FXT bestimmen und Bars FXT-tageweise im Buffer speichern
   $newDayOffset = $size + $fxtOffset/MINUTES;
   $bars1      = array_slice($bars, 0, $newDayOffset);
   $bars2      = array_slice($bars, $newDayOffset);

   $shortDate1 = date('D, d-M-Y', $bars1[0]['time_fxt']-$bars1[0]['delta_fxt']);    // Fri, 11-Jul-2003
   $shortDate2 = date('D, d-M-Y', $bars2[0]['time_fxt']-$bars2[0]['delta_fxt']);

   if (isSet($barBuffer[$type][$shortDate1])) {
      // Sicherstellen, daß die Daten zu mergender Bars nahtlos ineinander übergehen.
      $lastDelta = $barBuffer[$type][$shortDate1][sizeOf($barBuffer[$type][$shortDate1])-1]['delta_fxt'];
      $nextDelta = $bars1[0]['delta_fxt'];
      if ($lastDelta + 1*MINUTE != $nextDelta) throw new plRuntimeException('Bar delta mis-match, bars to merge: "'.value('dukaName', null, null, $type).'", $lastDelta='.$lastDelta.', $nextDelta='.$nextDelta);
      $barBuffer[$type][$shortDate1] = array_merge($barBuffer[$type][$shortDate1], $bars1);
   }
   else {
      $barBuffer[$type][$shortDate1] = $bars1;
   }

   if (isSet($barBuffer[$type][$shortDate2])) {
      // Sicherstellen, daß die Daten zu mergender Bars nahtlos ineinander übergehen.
      $lastDelta = $barBuffer[$type][$shortDate2][sizeOf($barBuffer[$type][$shortDate2])-1]['delta_fxt'];
      $nextDelta = $bars2[0]['delta_fxt'];
      if ($lastDelta + 1*MINUTE != $nextDelta) throw new plRuntimeException('Bar delta mis-match, bars to merge: "'.value('dukaName', null, null, $type).'", $lastDelta='.$lastDelta.', $nextDelta='.$nextDelta);
      $barBuffer[$type][$shortDate2] = array_merge($barBuffer[$type][$shortDate2], $bars2);
   }
   else {
      $barBuffer[$type][$shortDate2] = $bars2;
   }

   return true;
   /*
   // Sicherstellen, daß vorherige Tage im Buffer verarbeitet und gelöscht wurden.
   //if ($keys=array_keys($barBuffer[$type])) throw new plRuntimeException('Found unfinished bars of '.date('D, d-M-Y', $keys[0]).' while buffering bars of '.date('D, d-M-Y', $fxtDay));

   // Prüfen, ob die aktuellen Daten des Tages abgeschlossen sind (ist der Fall, wenn sie bis Mitternacht reichen)
   $bars = &$barBuffer[$type][$fxtDay];
   $size = sizeOf($bars);

   if ($bars[$size-1]['delta_fxt'] == 23*HOURS + 59*MINUTES)         // abgeschlossenen Tag weiterverarbeiten
      if (!processFinishedFxtBars($type, $fxtDay, $nameL, $fileL_rar, $fileL_bin))
         return false;
   */
}


/**
 * @return bool - Erfolgsstatus
 */
function processFinishedFxtBars($type, $fxtDay, $nameL, $fileL_rar, $fileL_bin) {
   global $barBuffer; $barBuffer[$type];
   if (!is_int($fxtDay))                               throw new IllegalTypeException('Illegal type of parameter $fxtDay: '.getType($fxtDay));
   if (!is_string($nameL))                             throw new IllegalTypeException('Illegal type of parameter $nameL: '.getType($nameL));
   if (!is_string($fileL_rar))                         throw new IllegalTypeException('Illegal type of parameter $fileL_rar: '.getType($fileL_rar));
   if (!is_null($fileL_bin) && !is_string($fileL_bin)) throw new IllegalTypeException('Illegal type of parameter $fileL_bin: '.getType($fileL_bin));


   // (1) Bars binär packen
   $bars         = &$barBuffer[$type][$fxtDay];
   $binaryString = null;

   foreach ($bars as $i => &$bar) {
      $binaryString .= pack('VVVVVV', $bar['time_fxt'],              // alle Felder als uint little-endian speichern
                                      $bar['open'    ],
                                      $bar['high'    ],
                                      $bar['low'     ],
                                      $bar['close'   ],
                                 (int)$bar['volume'  ]/100000);      // Units in Lots konvertieren
   }

   // (2) wenn Parameter $fileL_bin angegeben, binär gepackte Bars in Datei speichern
   if (!is_null($fileL_bin)) {
      mkDirWritable(dirName($fileL_bin));
      $tmpFile = tempNam(dirName($fileL_bin), baseName($fileL_bin));
      $hFile   = fOpen($tmpFile, 'wb');
      fWrite($hFile, $binaryString);
      fClose($hFile);
      if (is_file($fileL_bin)) unlink($fileL_bin);
      rename($tmpFile, $fileL_bin);                                  // So kann eine existierende Datei niemals korrupt sein.
   }

   // (3) binär gepackte Bars komprimieren und speichern

   // (4) Bars im Buffer löschen
   unset($barBuffer[$type][$fxtDay]);

   return true;
}


/**
 * Gibt dynamisch generierte Bezeichner zurück.
 *
 * Erzeugt, cacht und verwaltet ständig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
 * da die Variablen nicht über viele Funktionsaufrufe hinweg weitergereicht werden müssen, aber trotzdem nicht bei jeder
 * Verwendung neu evaluiert werden brauchen. Der Cache ist mit nur einigen zig Einträgen ausreichend groß.
 *
 * @param string $id     - eindeutiger Schlüssel des Bezeichners (ID)
 *
 * @param string $symbol - Symbol oder NULL
 * @param int    $time   - Timestamp oder NULL
 * @param string $type   - Kurstyp (bid|ask) oder NULL
 *
 * @return string
 */
function value($id, $symbol=null, $time=null, $type=null) {
   global $varCache;
   //static $varCache = array();
   if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time.'|'.$type), $varCache))
      return $varCache[$key];

   if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
   if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
   if (!is_null($type)) {
      if (!is_string($type))                     throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));
      if ($type!='bid' && $type!='ask')          throw new plInvalidArgumentException('Invalid parameter $type: "'.$type.'"');
   }


   if ($id == 'myfxName') {            // M1,Bid                                                         // lokaler Name
      if (!$type)   throw new plInvalidArgumentException('Invalid parameter $type: (null)');
      $result = 'M1,'.($type=='bid' ? 'Bid':'Ask');
   }
   else if ($id == 'myfxDirDate') {    // $yyyy/$mmL/$dd                                                 // lokales Pfad-Datum
      if (!$time)   throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $result = date('Y/m/d', $time);
   }
   else if ($id == 'myfxDir') {        // $dataDirectory/history/dukascopy/$symbol/$dateL                // lokales Verzeichnis
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      static $dataDirectory; if (!$dataDirectory)
      $dataDirectory = MyFX::getConfigPath('myfx.data_directory');
      $dateL         = value('myfxDirDate', null, $time, null);
      $result        = "$dataDirectory/history/dukascopy/$symbol/$dateL";
   }
   else if ($id == 'myfxFile.bin') {   // $myfxDir/$nameL.bin                                            // lokale Datei ungepackt
      $myfxDir = value('myfxDir' , $symbol, $time, null);
      $nameL   = value('myfxName', null, null, $type);
      $result  = "$myfxDir/$nameL.bin";
   }
   else if ($id == 'myfxFile.rar') {   // $myfxDir/$nameL.rar                                            // lokale Datei gepackt
      $myfxDir = value('myfxDir' , $symbol, $time, null);
      $nameL   = value('myfxName', null, null, $type);
      $result  = "$myfxDir/$nameL.rar";
   }
   else if ($id == 'dukaName') {       // BID_candles_min_1                                              // Dukascopy-Name
      if (!$type) throw new plInvalidArgumentException('Invalid parameter $type: (null)');
      $result = ($type=='bid' ? 'BID':'ASK').'_candles_min_1';
   }
   else if ($id == 'dukaFile.bin') {   // $myfxDir/$nameD.bin                                            // Dukascopy-Datei ungepackt
      $myfxDir = value('myfxDir' , $symbol, $time, null);
      $nameD   = value('dukaName', null, null, $type);
      $result  = "$myfxDir/$nameD.bin";
   }
   else if ($id == 'dukaFile.lzma') {  // $myfxDir/$nameD.bi5                                            // Dukascopy-Datei gepackt
      $myfxDir = value('myfxDir' , $symbol, $time, null);
      $nameD   = value('dukaName', null, null, $type);
      $result  = "$myfxDir/$nameD.bi5";
   }
   else if ($id == 'dukaUrlDate') {    // $yyyy/$mmD/$dd                                                 // Dukascopy-URL-Datum
      if (!$time)   throw new plInvalidArgumentException('Invalid parameter $time: '.$time);
      $yyyy   = date('Y', $time);
      $mmD    = strRight(iDate('m', $time)+ 99, 2);   // Januar = 00
      $dd     = date('d', $time);
      $result = "$yyyy/$mmD/$dd";
   }
   else if ($id == 'dukaUrl') {        // http://www.dukascopy.com/datafeed/$symbol/$dateD/$nameD.bi5    // Dukascopy-URL
      if (!$symbol) throw new plInvalidArgumentException('Invalid parameter $symbol: '.$symbol);
      $dateD  = value('dukaUrlDate', null, $time, null);
      $nameD  = value('dukaName'   , null, null, $type);
      $result = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$nameD.bi5";
   }
   else if ($id == 'dukaFile.404') {   // $myfxDir/$nameD.404                                            // Download-Fehlerdatei (404)
      $myfxDir = value('myfxDir' , $symbol, $time, null);
      $nameD   = value('dukaName', null, null, $type);
      $result  = "$myfxDir/$nameD.404";
   }
   else {
     throw new plInvalidArgumentException('Unknown parameter $id: "'.$id.'"');
   }

   $varCache[$key] = $result;
   (sizeof($varCache) > ($maxSize=64)) && array_shift($varCache) /*&& echoPre('cache size limit of '.$maxSize.' hit')*/;

   return $result;
}


/**
 *
 */
function showBuffer() {
   global $barBuffer;

   echoPre(NL);
   foreach ($barBuffer as $type => &$days) {
      if (!is_array($days)) {
         echoPre('barBuffer['.$type.'] => '.(is_null($days) ? 'null':$days));
         continue;
      }
      foreach ($days as $day => &$bars) {
         if (!is_array($bars)) {
            echoPre('barBuffer['.$type.']['.(is_int($day) ? date('D, d-M-Y', $day):$day).'] => '.(is_null($bars) ? 'null':$bars));
            continue;
         }
         $size = sizeOf($bars);
         $firstBar = $size ? date('H:i', $bars[0      ]['time_fxt']):null;
         $lastBar  = $size ? date('H:i', $bars[$size-1]['time_fxt']):null;
         echoPre('barBuffer['.$type.']['.(is_int($day) ? date('D, d-M-Y', $day):$day).'] => '.str_pad($size, 4, ' ', STR_PAD_LEFT).' bar'.($size==1?'':'s').($firstBar?'  '.$firstBar:'').($size>1?'-'.$lastBar:''));
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
      echo($message."\n");

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

 Syntax:  $self [symbol ...]


END;
}
?>
