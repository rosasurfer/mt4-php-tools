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
   case "alpari - demo (jacko) - 52256": { $account =     52256; $serverDirectory = 'AlpariUK-Demo'        ; $serverTimezone = 'Europe/Berlin'   ; break; }
   case "alpari - demo - 839407"       : { $account =    839407; $serverDirectory = 'AlpariUK-Demo'        ; $serverTimezone = 'Europe/Berlin'   ; break; }
   case "alpari - live - 818497"       : { $account =    818497; $serverDirectory = 'AlpariUK-Micro-2'     ; $serverTimezone = 'Europe/Berlin'   ; break; }
   case "alpari - live - 860960"       : { $account =    860960; $serverDirectory = 'AlpariUK-Micro-1'     ; $serverTimezone = 'Europe/Berlin'   ; break; }
   case "apbg - demo - 1106906"        : { $account =   1106906; $serverDirectory = 'APBGTrading-Server'   ; $serverTimezone = 'Europe/Berlin'   ; break; }
   case "atc - demo - 8199"            : { $account =      8199; $serverDirectory = 'ATCBrokers-Demo'      ; $serverTimezone = 'America/New_York'; break; }
   case "atc - live - 21529"           : { $account =     21529; $serverDirectory = 'ATCBrokers-Live'      ; $serverTimezone = 'America/New_York'; break; }
   case "fb capital - demo - 9947"     : { $account =      9947; $serverDirectory = 'ForexBaltic-Server'   ; $serverTimezone = 'Europe/London'   ; break; }
   case "mb trading - demo - 500452864": { $account = 500452864; $serverDirectory = 'MBTrading-Demo Server'; $serverTimezone = 'America/New_York'; break; }
   case "sig - live - 52464"           : { $account =     52464; $serverDirectory = 'SIG-Real.com'         ; $serverTimezone = 'Europe/Sofia'    ; break; }
   case "forex ltd - demo - 444868"    : { $account =    444868; $serverDirectory = 'FOREX-Server'         ; $serverTimezone = 'GMT'             ; break; }
   default:
      exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");
}
$serverDirectory = $parts[0].DIRECTORY_SEPARATOR.'history'.DIRECTORY_SEPARATOR.$serverDirectory;


// Quelldaten einlesen
$csvHistory = array();

$serverTimezone  = new DateTimeZone($serverTimezone);
$newYorkTimezone = new DateTimeZone('America/New_York');

$lines = file($sourceFile);
$headerChecked = false;
foreach ($lines as $i => &$line) {
   if ($line{0}=='#')                                                   // Kommentare überspringen
      continue;
   if (!$headerChecked) {                                               // Header in 1. Datenzeile prüfen
      if (trim($line) != "Ticket\tOpenTime\tOpenTimestamp\tDescription\tType\tSize\tSymbol\tOpenPrice\tStopLoss\tTakeProfit\tCloseTime\tCloseTimestamp\tClosePrice\tExpirationTime\tExpirationTimestamp\tMagicNumber\tCommission\tSwap\tNetProfit\tGrossProfit\tBalance\tComment")
         exit("Invalid file format (unexpected data header in line ".($i+1).")\n");
      $headerChecked = true;
      continue;
   }
   $values = explode("\t", $line);                                      // Spaltenanzahl prüfen
   if (sizeOf($values) != 22)                                           exit("Invalid file format (unexpected number of columns in line ".($i+1).")\n");

   // MT4-Zeiten in FXT und entsprechende GMT-Timestamps konvertieren (MetaTrader versteht nur GMT)
   $date = new DateTime(gmDate('Y-m-d H:i:s', $values[11]), $serverTimezone);
   $date->setTimezone($newYorkTimezone);
   $date->modify('+7 hours');

   $csvHistory[] = array((int) $values[11],                                // ServerTime
                         strToTime($date->format('Y-m-d H:i:s').' GMT'),   // GMT-Timestamp der FXT der ServerTime; FXT = America/New_York+0700
                         (float) $values[20]);                             // Balance
}
if (!$csvHistory)                                                       exit("Empty history file - nothing to do.\n");


// HistoryFile öffnen bzw. neu anlegen
$symbol   = $account.'.AB';                                             // AB = AccountBalance
$filename = $serverDirectory.DIRECTORY_SEPARATOR.$symbol.PERIOD_M5.'.hst';

$hFile = fOpen($filename, 'ab+');
if (fileSize($filename) < 148) {
   $hh = MT4Helper ::createHistoryHeader();
      $hh['symbol'  ] = $symbol;
      $hh['period'  ] = PERIOD_M5;
      $hh['digits'  ] = 2;
      $hh['timezone'] = 'FXT';
   MT4Helper ::writeHistoryHeader($hFile, $hh);
}

// Beginn- und Endzeitpunkt der Daten bestimmen
$start = $csvHistory[0][1];
$end   = $csvHistory[sizeOf($csvHistory)-1][1];

echoPre('writing data from '.gmDate('d.m.Y H:i:s', $start).' to '.gmDate('d.m.Y H:i:s', $end));



fClose($hFile);
unlink($filename);
?>
