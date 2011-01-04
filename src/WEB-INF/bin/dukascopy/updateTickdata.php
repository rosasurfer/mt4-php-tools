#!/usr/bin/php -Cq
<?php
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/../..'));       // WEB-INF-Verzeichnis einbinden, damit Konfigurationsdatei gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../../include/defines.php');
include(dirName(__FILE__).'/../../classes/classes.php');


define('APPLICATION_NAME', 'fx.pewasoft');


// Beginn der Tickdaten der einzelnen Pairs                          // Zeitzone der Dateien ist durchgehend GMT+0000 (keine Sommerzeit)
$pairs = array('AUDJPY' => strToTime('2011-01-04 00:01:15 GMT'),     // 2007-03-30 16:01:15 GMT
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
               'USDJPY' => strToTime('2007-03-30 16:01:15 GMT'),
               'USDNOK' => strToTime('2008-09-28 22:04:55 GMT'),
               'USDSEK' => strToTime('2008-09-28 23:30:31 GMT'));

$thisHour  = time();
$thisHour -= $thisHour % HOUR;

foreach ($pairs as $pair => $firstTick) {
   $firstHour = $firstTick - $firstTick % HOUR;

   for ($time=$firstHour; $time < $thisHour; $time+=HOUR) {    // Daten der aktuellen Stunde können noch nicht existieren
      date_default_timezone_set('America/New_York');
      $dow = date('w', $time + 7*HOURS);
      if ($dow==SATURDAY || $dow==SUNDAY)                      // Wochenenden überspringen, Sessionbeginn/-ende ist America/New_York+0700
         continue;

      date_default_timezone_set('GMT');
      $yyyy = date('Y', $time);
      $mm   = subStr(date('n', $time)+99, 1);                  // Januar = 00
      $dd   = date('d', $time);
      $hh   = date('H', $time);
      $url  = "http://www.dukascopy.com/datafeed/$pair/$yyyy/$mm/$dd/{$hh}h_ticks.bin";


      $request  = HttpRequest ::create()->setUrl($url);
      $response = CurlHttpClient ::create()->send($request);
      $status   = $response->getStatus();

      if ($status == 200) {
         $content = $response->getContent();
         echoPre("ok: $url");
      }
      elseif ($status == 404) {
         Logger ::log("File at \"$url\" doesn't exist (HTTP status $status)", L_WARN, __CLASS__);
      }
      else throw new RuntimeException("Unexpected HTTP status $status (".HttpResponse ::$sc[$status].") for url \"$url\"\n".printFormatted($response, true));
   }
   break;
}
?>
