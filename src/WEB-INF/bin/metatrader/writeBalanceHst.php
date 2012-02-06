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


// -- Funktionen -----------------------------------------------------------------------------------------------------------------------------------



// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente holen, auswerten und alle notwendigen Pfade und Dateinamen bestimmen
$args = getArgvArray();
if (!$args || !is_file($args[0]))                                    exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");

$sourceFile = realPath($args[0]);
$pathInfo   = pathInfo($sourceFile);
if (!isSet($pathInfo['extension']) || $pathInfo['extension']!='csv') exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");

$parts = explode(DIRECTORY_SEPARATOR.'experts'.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR, $pathInfo['dirname']);
if (sizeOf($parts) != 2)                                             exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");

switch (strToLower($parts[1])) {
 //case 'alpariuk-demo'        : { $serverTimezone = 'Europe/Berlin'   ; $account =     52256; break; } // Jacko
   case 'alpariuk-demo'        : { $serverTimezone = 'Europe/Berlin'   ; $account =    839407; break; }
   case 'alpariuk-micro-2'     : { $serverTimezone = 'Europe/Berlin'   ; $account =    818497; break; }
   case 'alpariuk-micro-1'     : { $serverTimezone = 'Europe/Berlin'   ; $account =    860960; break; }
   case 'apbgtrading-server'   : { $serverTimezone = 'Europe/Berlin'   ; $account =   1106906; break; }
   case 'atcbrokers-demo'      : { $serverTimezone = 'America/New_York'; $account =      8199; break; }
   case 'atcbrokers-live'      : { $serverTimezone = 'America/New_York'; $account =     21529; break; }
   case 'forexbaltic-server'   : { $serverTimezone = 'Europe/London'   ; $account =      9947; break; }
   case 'mbtrading-demo server': { $serverTimezone = 'America/New_York'; $account = 500452864; break; }
   case 'sig-real.com'         : { $serverTimezone = 'Europe/Kiev'     ; $account =     52464; break; }
   case 'forex-server'         : { $serverTimezone = 'GMT'             ; $account =    444868; break; }
   default:
      exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");
}
$serverDirectory = $parts[0].DIRECTORY_SEPARATOR.'history'.DIRECTORY_SEPARATOR.$parts[1];


// Quelldaten aus CSV-Datei einlesen
$csvHistory      = array();
$serverTimezone  = new DateTimeZone($serverTimezone);
$newYorkTimezone = new DateTimeZone('America/New_York');

$lines         = file($sourceFile);
$headerChecked = false;

foreach ($lines as $i => &$line) {
   if ($line{0}=='#')                                                      // Kommentare überspringen
      continue;
   if (!$headerChecked) {                                                  // Header in 1. Datenzeile prüfen
      if (trim($line) != "Ticket\tOpenTime\tOpenTimestamp\tDescription\tType\tSize\tSymbol\tOpenPrice\tStopLoss\tTakeProfit\tCloseTime\tCloseTimestamp\tClosePrice\tExpirationTime\tExpirationTimestamp\tMagicNumber\tCommission\tSwap\tNetProfit\tGrossProfit\tBalance\tComment")
         exit("Invalid file format (unexpected data header in line ".($i+1).")\n");
      $headerChecked = true;
      continue;
   }
   $values = explode("\t", $line);                                         // Spaltenanzahl prüfen
   if (sizeOf($values) != 22)                                              exit("Invalid file format (unexpected number of columns in line ".($i+1).")\n");

   // MT4-Zeiten in FXT konvertieren und entsprechende GMT-Timestamps berechnen (MetaTrader versteht nur GMT)
   $date = new DateTime(gmDate('Y-m-d H:i:s', $values[11]), $serverTimezone);
   $date->setTimezone($newYorkTimezone);
   $date->modify('+7 hours');
   $dow = $date->format('w');
   if ($dow==SATURDAY || $dow==SUNDAY) throw new plRuntimeException('Timestamp error for ticket #'.$values[0].': '.gmDate('"Y-m-d H:i:s \F\X\T" \i\s \a l', $values[11]));

   $csvHistory[] = array((int) $values[11],                                // ServerTime
                         strToTime($date->format('Y-m-d H:i:s \G\M\T')),   // GMT-Timestamp der FXT der ServerTime; FXT = America/New_York+0700
                         (float) $values[20]);                             // Balance
}
if (!$csvHistory)                                                          exit("Empty CSV history file - nothing to do.\n");


// MT4-HistoryFile öffnen bzw. neu anlegen
$symbol   = $account.'.AB';                                             // AB = AccountBalance
$period   = PERIOD_H1;
$filename = $serverDirectory.DIRECTORY_SEPARATOR.$symbol.$period.'.hst';

$hFile = fOpen($filename, 'ab+');
if (fileSize($filename) < 148) {
   $hh = MT4Helper ::createHistoryHeader();
      $hh['symbol'  ] = $symbol;
      $hh['period'  ] = $period;
      $hh['digits'  ] = 2;
      $hh['timezone'] = 'FXT';
   MT4Helper ::writeHistoryHeader($hFile, $hh);
}


// Start-, End- und aktuelle Bar ermitteln
$csvSize = sizeOf($csvHistory);
$start   = $csvHistory[0][1];
$end     = $csvHistory[$csvSize-1][1];

$now = new DateTime('@'.time());
$now->setTimezone($newYorkTimezone);
$now->modify('+7 hours');
$now = strToTime($now->format('Y-m-d H:i:s \G\M\T'));

$period *= MINUTES;

$startBar   = $start - $start % $period;
$endBar     = $end   - $end   % $period;
$currentBar = $now   - $now   % $period;
echoPre('startBar   = '.gmDate('d.m.Y H:i:s', $startBar  ));
echoPre('endBar     = '.gmDate('d.m.Y H:i:s', $endBar    ));
echoPre('currentBar = '.gmDate('d.m.Y H:i:s', $currentBar));


// Schleife über alle zu schreibenden Bars
$O = $H = $L = $C = $V = 0;

for ($i=$n=0, $time=$startBar; $time <= $currentBar; $time+=$period) {
   $dow = gmDate('w', $time);
   if ($dow==SATURDAY || $dow==SUNDAY)       // Wochenenden überspringen
      continue;
   ++$n;

   // Ticks der aktuellen Bar ermitteln
   $ticks  = null;
   $barEnd = $time + $period;
   for (; $i < $csvSize; ++$i) {
      if ($csvHistory[$i][1] >= $barEnd)     // keine weiteren Ticks für diese Bar
         break;
      $ticks[] = $csvHistory[$i][2];         // Ticks merken
   }

   // Bar formen
   if ($ticks) {
      $V = sizeOf($ticks);
      if ($C)
         array_unshift($ticks, $C);          // korrekt: Open = prevClose (nicht wie MetaTrader, das nur Ticks speichert)
      $O = $ticks[0];                        // Die Bar muß den Zeitraum und nicht die Ticks korrekt abbilden.
      $H = max($ticks);                      // Zur Veranschaulichung: 1 H1-Bar versus 4 M15-Bars und ein einziger Tick in den letzten 10 Minuten
      $L = min($ticks);
      $C = $ticks[sizeOf($ticks)-1];
   }
   else {
      $O = $H = $L = $C;                     // keine neuen Ticks, prevClose übernehmen
      $V = 0;
   }

   // Bar schreiben
   MT4Helper ::addHistoryBar($hFile, $time, $O, $H, $L, $C, $V);
}
echoPre($n.' bars');


fClose($hFile);
//unlink($filename);
?>
