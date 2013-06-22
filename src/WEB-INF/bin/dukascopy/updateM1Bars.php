#!/usr/bin/php -Cq
<?
/**
 * Aktualisiert die vorhandenen Dukascopy-M1-Daten.
 *
 * Webseite:      http://www.dukascopy.com/swiss/english/marketwatch/historical/
 *                http://www.dukascopy.com/free/candelabrum/
 *
 * Instrumente:   http://www.dukascopy.com/free/candelabrum/data.json
 *
 * History-Start: http://www.dukascopy.com/datafeed/metadata/HistoryStart.bi5  (ungepackt, unbekanntes Format)
 *
 * Daten-URLs:    1 Datei je Kalendertag ab History-Start, Januar = 00
 *                http://www.dukascopy.com/datafeed/GBPUSD/2013/05/10/BID_candles_min_1.bi5
 *                http://www.dukascopy.com/datafeed/GBPUSD/2013/05/10/ASK_candles_min_1.bi5
 *
 * Datenformat:   LZMA-gepackt, Timezone: GMT
 */
require(dirName(__FILE__).'/../../config.php');
date_default_timezone_set('GMT');


// History-Start der einzelnen Instrumente (geprüft am 21.06.2013)
$startTimes = array('AUDCAD' => strToTime('2005-12-26 00:00:00 GMT'),
                    'AUDCHF' => strToTime('2005-12-26 00:00:00 GMT'),
                    'AUDJPY' => strToTime('2003-11-30 00:00:00 GMT'),
                    'AUDNZD' => strToTime('2006-12-08 00:00:00 GMT'),
                    'AUDUSD' => strToTime('2003-08-03 00:00:00 GMT'),
                    'CADCHF' => strToTime('2005-12-26 00:00:00 GMT'),
                    'CADJPY' => strToTime('2004-10-20 00:00:00 GMT'),
                    'CHFJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                    'EURAUD' => strToTime('2005-10-02 00:00:00 GMT'),
                    'EURCAD' => strToTime('2004-10-20 00:00:00 GMT'),
                    'EURCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                    'EURGBP' => strToTime('2003-08-08 00:00:00 GMT'),
                    'EURJPY' => strToTime('2003-08-08 00:00:00 GMT'),
                    'EURNOK' => strToTime('2004-10-20 00:00:00 GMT'),
                    'EURNZD' => strToTime('2005-12-26 00:00:00 GMT'),
                    'EURSEK' => strToTime('2004-10-27 00:00:00 GMT'),
                    'EURUSD' => strToTime('2003-07-27 00:00:00 GMT'),
                    'GBPAUD' => strToTime('2006-01-01 00:00:00 GMT'),
                    'GBPCAD' => strToTime('2006-01-01 00:00:00 GMT'),
                    'GBPCHF' => strToTime('2003-08-08 00:00:00 GMT'),
                    'GBPJPY' => strToTime('2003-08-03 00:00:00 GMT'),
                    'GBPNZD' => strToTime('2006-01-01 00:00:00 GMT'),
                    'GBPUSD' => strToTime('2003-08-08 00:00:00 GMT'),
                    'NZDCAD' => strToTime('2006-01-01 00:00:00 GMT'),
                    'NZDCHF' => strToTime('2006-01-01 00:00:00 GMT'),
                    'NZDJPY' => strToTime('2006-01-01 00:00:00 GMT'),
                    'NZDUSD' => strToTime('2003-08-08 00:00:00 GMT'),
                    'USDCAD' => strToTime('2003-08-08 00:00:00 GMT'),
                    'USDCHF' => strToTime('2003-07-27 00:00:00 GMT'),
                    'USDJPY' => strToTime('2003-07-27 00:00:00 GMT'),
                    'USDNOK' => strToTime('2003-08-08 00:00:00 GMT'),
                    'USDSEK' => strToTime('2003-08-08 00:00:00 GMT'),
                    'USDSGD' => strToTime('2004-11-16 00:00:00 GMT'),
                    'XAGUSD' => strToTime('1997-08-13 00:00:00 GMT'),
                    'XAUUSD' => strToTime('1999-09-01 00:00:00 GMT'));


$today  = time();
$today -= $today % DAY;                                     // Heute 00:00 GMT


foreach ($startTimes as $symbol => $startTime) {
   $startTime -= $startTime % DAY;                          // 00:00 GMT des Starttages

   for ($time=$startTime; $time < $today; $time+=1*DAY) {   // heutigen Tag überspringen (Daten sind immer unvollständig)
      if (iDate('w', $time) == SATURDAY)                    // Samstage überspringen
         continue;

      // URLs zusammenstellen und laden
      $yyyy  = date('Y', $time);
      $mm    = subStr(iDate('m', $time)+99, 1);             // Januar = 00
      $dd    = date('d', $time);
      $path  = "$symbol/$yyyy/$mm/$dd";
      $file  = 'BID_candles_min_1.bi5';
      $url   = "http://www.dukascopy.com/datafeed/$path/$file";
      download($url, $path, $file);
   }
}


/**
 * Lädt eine URL und speichert die Antwort im Downloadverzeichnis (im angegebenen Unterverzeichnis und unter dem
 * angegebenen Dateinamen.
 *
 * @param string $url      - URL
 * @param string $path     - Unterverzeichnis
 * @param string $filename - Dateiname
 */
function download($url, $path, $filename) {
   if (!is_string($url))      throw new IllegalTypeException('Illegal type of argument $url: '.getType($url));
   if (!is_string($path))     throw new IllegalTypeException('Illegal type of argument $path: '.getType($path));
   if (!is_string($filename)) throw new IllegalTypeException('Illegal type of argument $filename: '.getType($filename));

   // Downloadverzeichnis bestimmen
   static $downloadDirectory = null;
   if (is_null($downloadDirectory))
      $downloadDirectory = MyFXHelper ::getAbsoluteConfigPath('history.dukascopy');

   // bereits heruntergeladene Dateien überspringen
   $path     = $downloadDirectory.'/'.$path;
   $filename = $path.'/'.$filename;
   if (is_file($filename) || is_file($filename.'.404')) {            // 404: URL, für die 404 zurückgegeben wurde
      echoPre("[Info]: Skipping url \"$url\"  (local file exists).");
      return;
   }

   // HTTP-Request abschicken und auswerten
   $request  = HttpRequest ::create()->setUrl($url);
   $response = CurlHttpClient ::create()->send($request);
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new plRuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));

   // ggf. Zielverzeichnis anlegen
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
   else {   // ... oder 404-Status mit leerer .404-Datei merken
      echoPre("[Info]: $status - File not found: \"$url\"");
      fClose(fOpen($filename.'.404', 'x'));
   }
}
?>
