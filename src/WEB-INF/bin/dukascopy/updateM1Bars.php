#!/usr/bin/php
<?php
/**
 * Aktualisiert die vorhandenen Dukascopy-M1-Daten. Die Daten werden heruntergeladen, nach FXT konvertiert und in einem
 * eigenen RAR-komprimierten binären Format gespeichert.
 *
 *
 * Webseite:      http://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                http://www.dukascopy.com/free/candelabrum/
 *
 * Instrumente:   http://www.dukascopy.com/free/candelabrum/data.json
 *
 * History-Start: http://www.dukascopy.com/datafeed/metadata/HistoryStart.bi5  (Format unbekannt)
 *
 * URL-Format:    Eine Datei je Kalendertag ab History-Start (inkl. Wochenenden, Januar = 00), z.B.:
 *                • http://www.dukascopy.com/datafeed/GBPUSD/2013/05/10/BID_candles_min_1.bi5
 *                • http://www.dukascopy.com/datafeed/GBPUSD/2013/05/10/ASK_candles_min_1.bi5
 *
 * Dateiformat:   binär, LZMA-gepackt, alle Zeiten in GMT
 *                Wochenenddateien enthalten durchgehend den Freitagsschlußkurs (OHLC) und ein Volume von 0 (zero).
 *
 *                @see Dukascopy::processBarFile()
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// History-Start der einzelnen Instrumente (geprüft am 21.06.2013)
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
$args = in_array('*', $args) ? array_keys($startTimes) : array_unique($args);    // '*' steht für und ersetzt alle Symbole


// (2) Daten aktualisieren
foreach ($args as $symbol) {
   if (!updateInstrument($symbol, $startTimes[$symbol]))
      exit(1);
}


// (3) Ende
exit(0);


// -- Ende -----------------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die M1-Daten eines Instruments.
 *
 * @param string $symbol    - Symbol des Instruments
 * @param int    $startTime - Beginn der Dukascopy-Daten dieses Instruments
 *
 * @return bool - Erfolgsstatus
 */
function updateInstrument($symbol, $startTime) {
   if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
   if (!is_int($startTime)) throw new IllegalTypeException('Illegal type of parameter $startTime: '.getType($startTime));

   static $dataDirectory = null;
   if (!$dataDirectory) $dataDirectory = MyFX ::getConfigPath('myfx.data_directory');


   echoPre(__FUNCTION__.'(): '.$symbol);
   return true;


   // (1) Prüfen, ob sich das Startdatum geändert hat

   // (2) Datei herunterladen:         00:00:00 - 23:59:59 GMT

   // (3) Daten nach FXT konvertieren: 02:00:00 - 01:59:59 FXT (vom ersten Tag fehlen 2 h)

   // (4) Daten bis 23:59:59 FXT speichern, die restlichen 2 h im Speicher behalten

   // (5) nächste Datei herunterladen und entpacken

   // (6) Daten mit den vom letzten Download verbliebenen 2 h mergen




   $symbol     = strToUpper($symbol);
   $startTime -= $startTime % DAY;                                   // 00:00 GMT des Starttages
   $today      = ($today=time()) - $today%DAY;                       // 00:00 GMT des aktuellen Tages


   for ($time=$startTime; $time < $today; $time+=1*DAY) {            // aktuellen Tag ignorieren (Daten sind immer unvollständig)
      if (iDate('w', $time) == SATURDAY)                             // Samstage überspringen (00:00 GMT = 02:00 FXT)
         continue;

      // URL und Dateinamen zusammenstellen
      $yyyy  = date('Y', $time);
      $mmD   = strRight(iDate('m', $time)+ 99, 2);                   // Dukascopy-Monat: Januar = 00
      $mmL   = strRight(iDate('m', $time)+100, 2);                   // lokaler Monat:   Januar = 01
      $dd    = date('d', $time);
      $dateD = "$yyyy/$mmD/$dd";                                     // Dukascopy-Datum
      $dateL = "$yyyy/$mmL/$dd";                                     // lokales Datum
      $file  = 'BID_candles_min_1';
      $url   = "http://www.dukascopy.com/datafeed/$symbol/$dateD/$file.bi5";
      $file  = "$dataDirectory/history/dukascopy/orig/$symbol/$dateL/$file.bin.lzma";

      // Existenz der Datei prüfen
      if (!is_file($file)) {
         if (is_file($file.'404')) {                                 // .404-Datei: vorheriger Response-Status 404
            echoPre("[Info]: Skipping $symbol data of $yyyy.$mmL.$dd (404 file exists)");
            continue;
         }
         // URL laden und speichern
         downloadUrl($url, $file);
         if (is_file($file.'404'))                                   // Response-Status 404
            continue;
         if (!is_file($file))
            echoPre("[Error]: Downloading $symbol data of $yyyy.$mmL.$dd failed") & exit(1);
      }

      // Datei verarbeiten
      Dukascopy ::processBarFile($file);

      // vorerst nach einer Datei abbrechen
      exit(0);
   }
   return true;
}


/**
 * Lädt eine URL und speichert die Antwort unter dem angegebenen Dateinamen.
 *
 * @param string $url      - URL
 * @param string $filename - vollständiger Dateiname
 */
function downloadUrl($url, $filename) {
   if (!is_string($url))      throw new IllegalTypeException('Illegal type of parameter $url: '.getType($url));
   if (!is_string($filename)) throw new IllegalTypeException('Illegal type of parameter $filename: '.getType($filename));

   // HTTP-Request abschicken und auswerten
   $request  = HttpRequest ::create()->setUrl($url);
   $response = CurlHttpClient ::create()->send($request);
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new plRuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));

   // ggf. Zielverzeichnis anlegen
   $path = dirName($filename);
   if (is_file($path))                              throw new plInvalidArgumentException('Cannot write to directory "'.$path.'" (is file)');
   if (!is_dir($path) && !mkDir($path, 0700, true)) throw new plInvalidArgumentException('Cannot create directory "'.$path.'"');
   if (!is_writable($path))                         throw new plInvalidArgumentException('Cannot write to directory "'.$path.'"');

   // Datei speichern ...
   if ($status == 200) {
      echoPre("[Ok]: $url");
      $hFile = fOpen($filename, 'xb');
      fWrite($hFile, $response->getContent());
      fClose($hFile);
   }
   else {
      // ... oder 404-Status merken
      echoPre("[Info]: $status - File not found: \"$url\"");
      fClose(fOpen($filename.'.404', 'x'));
   }
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
