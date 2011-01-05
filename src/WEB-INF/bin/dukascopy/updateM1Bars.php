#!/usr/bin/php -Cq
<?php
/**
 * Lädt periodische Dukascopy-Daten (S10, M1, M5, M10, M15, M30, H1-Candles). Die Dukascopy-Timeframes größer als H1
 * sind wegen fehlerhafter Zeitzoneneinstellung unbrauchbar.
 */
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/../..'));       // WEB-INF-Verzeichnis einbinden, damit Konfigurationsdatei gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../../include/defines.php');
include(dirName(__FILE__).'/../../classes/classes.php');


define('APPLICATION_NAME', 'fx.pewasoft');


// Beginn der Daten der einzelnen Pairs in den einzelnen Perioden (Zeitzone ist durchgehend GMT+0000, ohne Sommerzeit)
$data = array('S10' => array('AUDJPY' => strToTime(''),
                             'AUDNZD' => strToTime(''),
                             'AUDUSD' => strToTime(''),
                             'CADJPY' => strToTime(''),
                             'CHFJPY' => strToTime(''),
                             'EURAUD' => strToTime(''),
                             'EURCAD' => strToTime(''),
                             'EURCHF' => strToTime(''),
                             'EURGBP' => strToTime(''),
                             'EURJPY' => strToTime(''),
                             'EURNOK' => strToTime(''),
                             'EURSEK' => strToTime(''),
                             'EURUSD' => strToTime(''),
                             'GBPCHF' => strToTime(''),
                             'GBPJPY' => strToTime(''),
                             'GBPUSD' => strToTime(''),
                             'NZDUSD' => strToTime(''),
                             'USDCAD' => strToTime(''),
                             'USDCHF' => strToTime(''),
                             'USDDKK' => strToTime(''),
                             'USDJPY' => strToTime(''),
                             'USDNOK' => strToTime(''),
                             'USDSEK' => strToTime(''),
              ),
              'M1'  => array('AUDJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                             'AUDNZD' => strToTime('2008-12-22 16:16:02 GMT'),
                             'AUDUSD' => strToTime('2007-03-30 16:01:16 GMT'),
                             'CADJPY' => strToTime('2007-03-30 16:01:16 GMT'),
                             'CHFJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                             'EURAUD' => strToTime('2007-03-30 16:01:19 GMT'),
                             'EURCAD' => strToTime('2008-09-23 11:32:09 GMT'),
                             'EURCHF' => strToTime('2007-03-30 16:01:15 GMT'),
                             'EURGBP' => strToTime('2007-03-30 16:01:17 GMT'),
                             'EURJPY' => strToTime('2007-03-30 16:01:16 GMT'),
                             'EURNOK' => strToTime('2007-03-30 16:01:19 GMT'),
                             'EURSEK' => strToTime('2007-03-30 16:01:31 GMT'),
                             'EURUSD' => strToTime('2007-03-30 16:01:15 GMT'),
                             'GBPCHF' => strToTime('2007-03-30 16:01:15 GMT'),
                             'GBPJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                             'GBPUSD' => strToTime('2007-03-30 16:01:15 GMT'),
                             'NZDUSD' => strToTime('2007-03-30 16:01:53 GMT'),
                             'USDCAD' => strToTime('2007-03-30 16:01:16 GMT'),
                             'USDCHF' => strToTime('2007-03-30 16:01:15 GMT'),
                             'USDDKK' => strToTime('2007-03-30 16:01:15 GMT'),
                             'USDJPY' => strToTime('2007-03-30 16:01:15 GMT'),
                             'USDNOK' => strToTime('2008-09-28 22:04:55 GMT'),
                             'USDSEK' => strToTime('2008-09-28 23:30:31 GMT'),
              ),
              'M5'  => array('AUDJPY' => strToTime(''),
                             'AUDNZD' => strToTime(''),
                             'AUDUSD' => strToTime(''),
                             'CADJPY' => strToTime(''),
                             'CHFJPY' => strToTime(''),
                             'EURAUD' => strToTime(''),
                             'EURCAD' => strToTime(''),
                             'EURCHF' => strToTime(''),
                             'EURGBP' => strToTime(''),
                             'EURJPY' => strToTime(''),
                             'EURNOK' => strToTime(''),
                             'EURSEK' => strToTime(''),
                             'EURUSD' => strToTime(''),
                             'GBPCHF' => strToTime(''),
                             'GBPJPY' => strToTime(''),
                             'GBPUSD' => strToTime(''),
                             'NZDUSD' => strToTime(''),
                             'USDCAD' => strToTime(''),
                             'USDCHF' => strToTime(''),
                             'USDDKK' => strToTime(''),
                             'USDJPY' => strToTime(''),
                             'USDNOK' => strToTime(''),
                             'USDSEK' => strToTime(''),
              ),
              'M10' => array('AUDJPY' => strToTime(''),
                             'AUDNZD' => strToTime(''),
                             'AUDUSD' => strToTime(''),
                             'CADJPY' => strToTime(''),
                             'CHFJPY' => strToTime(''),
                             'EURAUD' => strToTime(''),
                             'EURCAD' => strToTime(''),
                             'EURCHF' => strToTime(''),
                             'EURGBP' => strToTime(''),
                             'EURJPY' => strToTime(''),
                             'EURNOK' => strToTime(''),
                             'EURSEK' => strToTime(''),
                             'EURUSD' => strToTime(''),
                             'GBPCHF' => strToTime(''),
                             'GBPJPY' => strToTime(''),
                             'GBPUSD' => strToTime(''),
                             'NZDUSD' => strToTime(''),
                             'USDCAD' => strToTime(''),
                             'USDCHF' => strToTime(''),
                             'USDDKK' => strToTime(''),
                             'USDJPY' => strToTime(''),
                             'USDNOK' => strToTime(''),
                             'USDSEK' => strToTime(''),
              ),
              'M15' => array('AUDJPY' => strToTime(''),
                             'AUDNZD' => strToTime(''),
                             'AUDUSD' => strToTime(''),
                             'CADJPY' => strToTime(''),
                             'CHFJPY' => strToTime(''),
                             'EURAUD' => strToTime(''),
                             'EURCAD' => strToTime(''),
                             'EURCHF' => strToTime(''),
                             'EURGBP' => strToTime(''),
                             'EURJPY' => strToTime(''),
                             'EURNOK' => strToTime(''),
                             'EURSEK' => strToTime(''),
                             'EURUSD' => strToTime(''),
                             'GBPCHF' => strToTime(''),
                             'GBPJPY' => strToTime(''),
                             'GBPUSD' => strToTime(''),
                             'NZDUSD' => strToTime(''),
                             'USDCAD' => strToTime(''),
                             'USDCHF' => strToTime(''),
                             'USDDKK' => strToTime(''),
                             'USDJPY' => strToTime(''),
                             'USDNOK' => strToTime(''),
                             'USDSEK' => strToTime(''),
              ),
              'M30' => array('AUDJPY' => strToTime(''),
                             'AUDNZD' => strToTime(''),
                             'AUDUSD' => strToTime(''),
                             'CADJPY' => strToTime(''),
                             'CHFJPY' => strToTime(''),
                             'EURAUD' => strToTime(''),
                             'EURCAD' => strToTime(''),
                             'EURCHF' => strToTime(''),
                             'EURGBP' => strToTime(''),
                             'EURJPY' => strToTime(''),
                             'EURNOK' => strToTime(''),
                             'EURSEK' => strToTime(''),
                             'EURUSD' => strToTime(''),
                             'GBPCHF' => strToTime(''),
                             'GBPJPY' => strToTime(''),
                             'GBPUSD' => strToTime(''),
                             'NZDUSD' => strToTime(''),
                             'USDCAD' => strToTime(''),
                             'USDCHF' => strToTime(''),
                             'USDDKK' => strToTime(''),
                             'USDJPY' => strToTime(''),
                             'USDNOK' => strToTime(''),
                             'USDSEK' => strToTime(''),
              ),
              'H1'  => array('AUDJPY' => strToTime(''),
                             'AUDNZD' => strToTime(''),
                             'AUDUSD' => strToTime(''),
                             'CADJPY' => strToTime(''),
                             'CHFJPY' => strToTime(''),
                             'EURAUD' => strToTime(''),
                             'EURCAD' => strToTime(''),
                             'EURCHF' => strToTime(''),
                             'EURGBP' => strToTime(''),
                             'EURJPY' => strToTime(''),
                             'EURNOK' => strToTime(''),
                             'EURSEK' => strToTime(''),
                             'EURUSD' => strToTime(''),
                             'GBPCHF' => strToTime(''),
                             'GBPJPY' => strToTime(''),
                             'GBPUSD' => strToTime(''),
                             'NZDUSD' => strToTime(''),
                             'USDCAD' => strToTime(''),
                             'USDCHF' => strToTime(''),
                             'USDDKK' => strToTime(''),
                             'USDJPY' => strToTime(''),
                             'USDNOK' => strToTime(''),
                             'USDSEK' => strToTime(''),
              ));


$today  = time();
$today -= $today % DAY;


foreach ($data['M1'] as $symbol => $start) {
   $start -= $start % DAY;

   for ($time=$start; $time < $today; $time+=DAY) {         // Daten des heutigen Tages können noch nicht existieren
      date_default_timezone_set('America/New_York');
      $startDoW = date('w', $time + 7*HOURS);
      $endDoW   = date('w', $time + 7*HOURS+DAY);
      if ($startDoW==SATURDAY || $endDoW==SUNDAY)           // Wochenenden überspringen, Sessionbeginn/-ende ist America/New_York+0700
         continue;

      // URLs zusammenstellen und laden
      date_default_timezone_set('GMT');
      $year  = date('Y', $time);
      $month = subStr(date('n', $time)+99, 1);                       // Januar = 00
      $day   = date('d', $time);
      $path  = "$symbol/$year/$month/$day";
      $file  = 'BID_candles_min_1.bin';
      $url   = "http://www.dukascopy.com/datafeed/$path/$file";
      download($url, $path, $file);

      $file  = 'ASK_candles_min_1.bin';
      $url   = "http://www.dukascopy.com/datafeed/$path/$file";
      download($url, $path, $file);
   }
   break;
}


/**
 * Lädt eine URL und speichert die Antwort unter dem angegebenen Dateinamen.
 *
 * @param string $url      - URL
 * @param string $path     - Verzeichnis, in dem die Datei gespeichert werden soll
 * @param string $filename - Dateiname
 */
function download($url, $path, $filename) {
   if (!is_string($url))      throw new IllegalTypeException('Illegal type of argument $url: '.getType($url));
   if (!is_string($path))     throw new IllegalTypeException('Illegal type of argument $path: '.getType($path));
   if (!is_string($filename)) throw new IllegalTypeException('Illegal type of argument $filename: '.getType($filename));

   echoPre("url = $url");
   return;

   // Downloadverzeichnis bestimmen
   static $downloadDirectory = null;
   if ($downloadDirectory === null)
      $downloadDirectory = realPath(PROJECT_DIRECTORY.'/'.Config ::get('dukascopy.download_directory'));

   // bereits heruntergeladene Dateien überspringen
   $path = $downloadDirectory.PATH_SEPARATOR.$path;
   $file = $path.PATH_SEPARATOR.$file;
   if (is_file($file) || is_file($file.'.404')) {       // Datei, für die 404 zurückgegeben wurde
      echoPre("[Info]: Skipping url \"$url\", local file already exists.");
      return;
   }

   // HTTP-Request abschicken und auswerten
   $request  = HttpRequest ::create()->setUrl($url);
   $response = CurlHttpClient ::create()->send($request);
   $status   = $response->getStatus();
   if ($status!=200 && $status!=404) throw new RuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));

   // ggf. Zielverzeichnis anlegen
   if (is_file($path) || (!is_writable($path) && !mkDir($path, 0700, true))) throw new RuntimeException("Can not write to directory \"$path\"");

   // Datei speichern ...
   if ($status == 200) {
      echoPre("[Ok]: $url");
      $hFile = fOpen($file, 'xb');
      fWrite($hFile, $response->getContent());
      fClose($hFile);
   }
   else {   // ... oder 404-Status mit leerer .404-Datei merken
      echoPre("[Info]: $status - File not found: \"$url\"");
      fClose(fOpen($file.'.404', 'x'));
   }
}
?>
