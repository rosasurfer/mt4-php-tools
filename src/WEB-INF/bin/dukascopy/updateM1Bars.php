#!/usr/bin/php -Cq
<?php
/**
 * Lädt periodische Dukascopy-Daten (Candles).
 */

set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/../..'));       // WEB-INF-Verzeichnis einbinden, damit Konfigurationsdatei gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../../include/defines.php');
include(dirName(__FILE__).'/../../classes/classes.php');


define('APPLICATION_NAME', 'fx.pewasoft');


// Beginn der Tickdaten der einzelnen Pairs                          // Zeitzone der Dateien ist durchgehend GMT+0000 (keine Sommerzeit)
$pairs = array('AUDJPY' => strToTime('2007-03-30 16:01:15 GMT'),
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

// Downloadverzeichnis bestimmen
$downloadDirectory = realPath(PROJECT_DIRECTORY.'/'.Config ::get('dukascopy.download_directory'));

echoPre("downloadDirectory = $downloadDirectory");
?>
