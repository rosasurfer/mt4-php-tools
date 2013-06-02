#!/usr/bin/php -Cq
<?php
/**
 * Listed die Headerinformationen der angebenen MT4-Historydateien auf.
 */
set_time_limit(0);
ini_set('include_path', realPath(dirName(__FILE__).'/..'));          // WEB-INF-Verzeichnis einbinden, damit Konfiguration gefunden wird

define('APPLICATION_NAME', 'myfx.pewasoft');
define('APPLICATION_ROOT', join(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, dirName(__FILE__)), 0, -2)));

// PHPLib und Klassendefinitionen einbinden
require(dirName(__FILE__).'/../../../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/../classes/defines.php');
include(dirName(__FILE__).'/../classes/classes.php');


// -- Funktionen -----------------------------------------------------------------------------------------------------------------------------------


/**
 * Gibt die lesbare Version eines Timeframe-Codes zurück.
 *
 * @param  int period - Timeframe-Code
 *
 * @return string
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


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenparameter holen
$args = array_slice($_SERVER['argv'], 1);
if (!$args) {
   exit("\n  Syntax: mt4History <file-pattern>\n");
}


// Dateien einlesen                    // TODO: glob() unterscheidet beim Patternmatching unter Windows fälschlich Groß-/Kleinschreibung
$files = glob($args[0], GLOB_ERR);     //       Solution: glob() mit Directory-Funktionen emulieren


// gefundene Dateien sortieren (order by Symbol ASC, Periode ASC)
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


// Tabellenheader ausgeben
echoPre("Symbol           Digits  Timesign             LastSync                  Bars  From                 To");
echoPre("----------------------------------------------------------------------------------------------------------------------");

// Zeilenformat definieren
$lineFormat = '%-15s    %d     %-19s  %-19s  %9s  %-19s  %-19s';


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
      $header = unpack('Vversion/a64description/a12symbol/Vperiod/Vdigits/Vtimesign/Vlastsync/a52reserved', fRead($hFile, 148));
      $header['description'] = current(explode("\0", $header['description'], 2));
      $header['symbol'     ] = current(explode("\0", $header['symbol'     ], 2));

      $rateinfoFrom = $rateinfoTo = array('time' => 0);

      if ($bars) {
         $rateinfoFrom = unpack('Vtime/dopen/dlow/dhigh/dclose/dvol', fRead($hFile, 44));
         if ($bars > 1) {
            fSeek($hFile, 148 + 44*($bars-1));
            $rateinfoTo   = unpack('Vtime/dopen/dlow/dhigh/dclose/dvol', fRead($hFile, 44));
         }
      }
      fClose($hFile);

      extract($header);
      $symbolperiod = $symbol.','.periodToString($period);
      $timesign     = $timesign ? date('Y.m.d H:i:s', $timesign):'';
      $lastsync     = $lastsync ? date('Y.m.d H:i:s', $lastsync):'';
      $ratesFrom    = $rateinfoFrom['time'] ? gmDate('Y.m.d H:i:s', $rateinfoFrom['time']):'';
      $ratesTo      = $rateinfoTo  ['time'] ? gmDate('Y.m.d H:i:s', $rateinfoTo  ['time']):'';
      echoPre(sprintf($lineFormat, $symbolperiod, $digits, $timesign, $lastsync, number_format($bars), $ratesFrom, $ratesTo));
   }
}
?>
