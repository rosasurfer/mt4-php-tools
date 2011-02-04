#!/usr/bin/php -Cq
<?php
/**
 * Listed die Headerinformationen der in der Befehlszeile übergebenen History-Dateien auf.
 */
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/..'));          // WEB-INF-Verzeichnis einbinden, damit Konfiguration gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../classes/defines.php');
include(dirName(__FILE__).'/../classes/classes.php');

define('APPLICATION_NAME', 'myfx.pewasoft');


// -------------------------------------------------------------------------------------------------------------------------------------------------

// Befehlszeilenparameter holen
$args = getArgvArray();
if (!$args) {
   printUsage();
   exit(1);
}


// Dateien einlesen
$files = glob($args[0], GLOB_ERR);


// gefundene Dateien sortieren (by Symbol ASC, Periode ASC)
$matches = array();
foreach ($files as $name) {
   if (preg_match('/^([^.]*\D)(\d+)(\.hst)$/i', $name, $match)) {
      $match[1] = strToUpper($match[1]);
      $match[3] = strToLower($match[3]);

      $matches[] = $match[1].$match[2].$match[3];
      $symbols[] =       $match[1];
      $periods[] = (int) $match[2];
   }
}
if (!$matches) exit("No history files found for \"$args[0]\"\n");
array_multisort($symbols, SORT_ASC, $periods, SORT_ASC, $matches);
$files = $matches;


// Dateien öffnen und Headerinfos auslesen
foreach ($files as $filename) {
   $hFile = fOpen($filename, 'rb');

   fSeek($hFile, 88);
   $data = fRead($hFile, 8);

   if (fEOF($hFile)) echoPre(str_pad($filename, 20).' - not a valid history file');
   else {
      $struct = unpack('Vtimesign/Vlast_sync', $data);
      echoPre(str_pad($filename, 20).'   timesign = '.($struct['timesign'] ? date('Y.m.d H:i:s', $struct['timesign']):'0').'   last_sync = '.($struct['last_sync'] ? date('Y.m.d H:i:s', $struct['last_sync']):'0'));
   }
   fClose($hFile);
}
exit();


/*
struct HistoryHeader {
  int    version;          // database version
  char   copyright[64];    // copyright info
  char   symbol[12];       // symbol name
  int    period;           // symbol timeframe
  int    digits;           // amount of digits after decimal point
  time_t timesign;         // time of database creation
  time_t last_sync;        // last synchronization time
  int    reserved[13];     // to be used in future
};

struct RateInfo {
  time_t ctm;              // bar time
  double open;
  double low;
  double high;
  double close;
  double vol;
};
*/

// -------------------------------------------------------------------------------------------------------------------------------------------------

/**
 * Syntax error, print help screen.
 */
function printUsage() {
   print <<<EOD

  Syntax: listHistoryFiles <file-pattern>

EOD;
}
?>
