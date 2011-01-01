#!/usr/bin/php -Cq
<?php
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/../..'));    // WEB-INF-Verzeichnis einbinden, damit Konfigurationsdatei gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../../include/defines.php');
include(dirName(__FILE__).'/../../classes/classes.php');


define('APPLICATION_NAME', 'fx.pewasoft');


date_default_timezone_set('UTC');         // Zeitzone der Dukascopy-Dateien ist GMT+0000 ohne Sommerzeit


// Beginn der Tickdaten der einzelnen Pairs
$pairs = array('AUDJPY' => strToTime('2007-03-30 16:01:15'),      // 2007-03-30 16:01:15
               'AUDNZD' => strToTime('2008-12-22 16:16:02'),
               'AUDUSD' => strToTime('2007-03-30 16:01:15'),
               'CADJPY' => strToTime('2007-03-30 16:01:15'),
               'CHFJPY' => strToTime('2007-03-30 16:01:15'),
               'EURAUD' => strToTime('2007-03-30 16:01:15'),
               'EURCAD' => strToTime('2008-09-23 11:32:09'),
               'EURCHF' => strToTime('2007-03-30 16:01:15'),
               'EURGBP' => strToTime('2007-03-30 16:01:15'),
               'EURJPY' => strToTime('2007-03-30 16:01:15'),
               'EURNOK' => strToTime('2007-03-30 16:01:15'),
               'EURSEK' => strToTime('2007-03-30 16:01:15'),
               'EURUSD' => strToTime('2007-03-30 16:01:15'),
               'GBPCHF' => strToTime('2007-03-30 16:01:15'),
               'GBPJPY' => strToTime('2007-03-30 16:01:15'),
               'GBPUSD' => strToTime('2007-03-30 16:01:15'),
               'NZDUSD' => strToTime('2007-03-30 16:01:15'),
               'USDCAD' => strToTime('2007-03-30 16:01:15'),
               'USDCHF' => strToTime('2007-03-30 16:01:15'),
               'USDJPY' => strToTime('2007-03-30 16:01:15'),
               'USDNOK' => strToTime('2008-09-28 22:04:55'),
               'USDSEK' => strToTime('2008-09-28 23:30:31'));

$now         = time();
$currentHour = $now - $now % HOUR;

$n = 0;
foreach ($pairs as $pair => $firstTick) {
   $firstHour = $firstTick - $firstTick % HOUR;

   for ($i=$firstHour; $i < $currentHour; $i+=HOUR) {       // Daten der aktuellen Stunde können noch nicht existieren
      $yyyy = date('Y', $i);
      $mm   = date('m', $i);
      $dd   = date('d', $i);
      $hh   = date('H', $i);
      $dow  = date('w', $i);

      if ($dow==SATURDAY || ($dow==SUNDAY && $hh < 22))     // Wochenenden überspringen, Montag beginnt um "Sun 22:00 GMT"
         continue;                                          // TODO: Welche Sommerzeit verwendet Dukascopy???

      $url  = "http://www.dukascopy.com/datafeed/$pair/$yyyy/$mm/$dd/{$hh}h_ticks.bin";
      echoPre($url);
      ++$n;
   }
   //break;
}
echoPre($n.' urls');
?>
