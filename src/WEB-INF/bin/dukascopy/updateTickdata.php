#!/usr/bin/php -Cq
<?php
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/../..'));    // WEB-INF-Verzeichnis einbinden, damit Konfigurationsdatei gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../../include/defines.php');
include(dirName(__FILE__).'/../../classes/classes.php');


define('APPLICATION_NAME', 'fx.pewasoft');


// Beginn der Tickdaten der einzelnen Pairs
date_default_timezone_set('GMT');                                 // Zeitzone der Dateien ist durchgehend GMT+0000 (keine Sommerzeit)
$pairs = array('AUDJPY' => strToTime('2010-12-24 16:01:15'),      // 2007-03-30 16:01:15
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

foreach ($pairs as $pair => $firstTick) {
   $firstHour = $firstTick - $firstTick % HOUR;

   for ($time=$firstHour; $time < $currentHour; $time+=HOUR) {    // Daten der aktuellen Stunde können noch nicht existieren
      date_default_timezone_set('America/New_York');
      $dow  = date('w', $time + 7*HOURS);
      if ($dow==SATURDAY || $dow==SUNDAY)                         // Wochenenden überspringen, Maßstab ist America/New_York+7000
         continue;

      date_default_timezone_set('GMT');
      $yyyy = date('Y', $time);
      $mm   = date('m', $time);
      $dd   = date('d', $time);
      $hh   = date('H', $time);

      $url  = "http://www.dukascopy.com/datafeed/$pair/$yyyy/$mm/$dd/{$hh}h_ticks.bin";
      echoPre($url);
   }
   break;
}
?>
