#!/usr/bin/php -Cq
<?php
/**
 * Listed die Headerinformationen der in der Befehlszeile angebenen History-Dateien auf.
 */
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/..'));          // WEB-INF-Verzeichnis einbinden, damit Konfiguration gefunden wird

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../classes/defines.php');
include(dirName(__FILE__).'/../classes/classes.php');

define('APPLICATION_NAME', 'myfx.pewasoft');



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
   if (preg_match('/^([^.]*\D)(\d+)(\.[^.]*)*\.hst$/i', $name, $match)) {
      $symbols[] = strToUpper($match[1]);
      $periods[] = (int) $match[2];
      $matches[] = $name;
   }
}
if (!$matches) exit("No history files found for \"$args[0]\"\n");
array_multisort($symbols, SORT_ASC, $periods, SORT_ASC, $matches);


// Tabellenheader ausgeben und Zeilenformat definieren
echoPre("File                   Symbol           Digits  Timesign             LastSync               Bars  From                 To");
echoPre("------------------------------------------------------------------------------------------------------------------------------------------");
$lineFormat = '%-21s  %-15s    %d     %-19s  %-19s%8s  %-19s  %-19s';


// Dateien öffnen und auslesen
foreach ($matches as $i => $filename) {
   $filesize = fileSize($filename);
   if ($filesize < 148) {
      $invalid = true;
      echoPre(str_pad($filename, 21).' not a valid history file');
   }
   else {
      $bars = floor(($filesize-148)/44);

      $hFile  = fOpen($filename, 'rb');
      $header = unpack('Vversion/a64description/a12symbol/Vperiod/Vdigits/Vtimesign/Vlastsync/a13reserved', fRead($hFile, 148));
      $header['description'] = current(explode("\0", $header['description'], 2));
      $header['symbol'     ] = current(explode("\0", $header['symbol'     ], 2));

      $rateinfoFrom = unpack('Vtime/dopen/dlow/dhigh/dclose/dvol', fRead($hFile, 44));
      fSeek($hFile, 148 + 44*($bars-1));
      $rateinfoTo   = unpack('Vtime/dopen/dlow/dhigh/dclose/dvol', fRead($hFile, 44));
      fClose($hFile);

      extract($header);
      $symbolperiod = $symbol.','.periodToString($period);
      $timesign     = $timesign ? date('Y.m.d H:i:s', $timesign):'0';
      $lastsync     = $lastsync ? date('Y.m.d H:i:s', $lastsync):'0';
      $ratesFrom    = gmDate('Y.m.d H:i:s', $rateinfoFrom['time']);
      $ratesTo      = gmDate('Y.m.d H:i:s', $rateinfoTo  ['time']);
      echoPre(sprintf($lineFormat, $filename, $symbolperiod, $digits, $timesign, $lastsync, number_format($bars), $ratesFrom, $ratesTo));
   }
}
exit(0);


/*
typedef struct _HISTORY_HEADER {
  int  version;            //     4      => hh[ 0]    // database version
  char description[64];    //    64      => hh[ 1]    // ie. copyright info
  char symbol[12];         //    12      => hh[17]    // symbol name
  int  period;             //     4      => hh[20]    // symbol timeframe
  int  digits;             //     4      => hh[21]    // amount of digits after decimal point
  int  timesign;           //     4      => hh[22]    // creation timestamp
  int  lastsync;           //     4      => hh[23]    // last synchronization timestamp
  int  reserved[13];       //    52      => hh[24]    // to be used in future
} HISTORY_HEADER, hh;      // = 148 byte = int[37]

typedef struct _RATEINFO {
  int    time;             //     4      =>  ri[0]    // bar time
  double open;             //     8      =>  ri[1]
  double low;              //     8      =>  ri[3]
  double high;             //     8      =>  ri[5]
  double close;            //     8      =>  ri[7]
  double vol;              //     8      =>  ri[9]
} RATEINFO, ri;            //  = 44 byte = int[11]
*/


// -- Funktionen -----------------------------------------------------------------------------------------------------------------------------------


/**
 *
 */
function periodToString($period) {
   switch ($period) {
      case PERIOD_M1  : return("M1" );     //     1  1 minute
      case PERIOD_M5  : return("M5" );     //     5  5 minutes
      case PERIOD_M15 : return("M15");     //    15  15 minutes
      case PERIOD_M30 : return("M30");     //    30  30 minutes
      case PERIOD_H1  : return("H1" );     //    60  1 hour
      case PERIOD_H4  : return("H4" );     //   240  4 hour
      case PERIOD_D1  : return("D1" );     //  1440  daily
      case PERIOD_W1  : return("W1" );     // 10080  weekly
      case PERIOD_MN1 : return("MN1");     // 43200  monthly
   }
   return("$period");
}


/**
 * Syntax error, print help screen.
 */
function printUsage() {
   echo("\n  Syntax: mt4History <file-pattern>\n");
}
?>
