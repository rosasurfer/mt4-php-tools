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

// define('AH_TICKET             ',  0);
// define('AH_OPENTIME           ',  1);
// define('AH_OPENTIMESTAMP      ',  2);
// define('AH_TYPEDESCRIPTION    ',  3);
// define('AH_TYPE               ',  4);
// define('AH_SIZE               ',  5);
// define('AH_SYMBOL             ',  6);
// define('AH_OPENPRICE          ',  7);
// define('AH_STOPLOSS           ',  8);
// define('AH_TAKEPROFIT         ',  9);
// define('AH_CLOSETIME          ', 10);
// define('AH_CLOSETIMESTAMP     ', 11);
// define('AH_CLOSEPRICE         ', 12);
// define('AH_EXPIRATIONTIME     ', 13);
// define('AH_EXPIRATIONTIMESTAMP', 14);
// define('AH_MAGICNUMBER        ', 15);
// define('AH_COMMISSION         ', 16);
// define('AH_SWAP               ', 17);
// define('AH_NETPROFIT          ', 18);
// define('AH_GROSSPROFIT        ', 19);
// define('AH_BALANCE            ', 20);
// define('AH_COMMENT            ', 21);


// -- Funktionen -----------------------------------------------------------------------------------------------------------------------------------



// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente holen, auswerten und alle notwendigen Pfade und Dateinamen bestimmen
$args = getArgvArray();
if (!$args || !is_file($args[0]))
   exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");

$sourceFile = realPath($args[0]);
$pathInfo   = pathInfo($sourceFile);
if (!isSet($pathInfo['extension']) || $pathInfo['extension']!='csv')
   exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");

$parts = explode(DIRECTORY_SEPARATOR.'experts'.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR, $pathInfo['dirname']);
if (sizeOf($parts) != 2)
   exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");

switch (strToLower($parts[1])) {
   case "alpari - demo (jacko) - 52256": { $serverDirectory = 'AlpariUK-Demo'        ; $account =     52256; break; }
   case "alpari - demo - 839407"       : { $serverDirectory = 'AlpariUK-Demo'        ; $account =    839407; break; }
   case "alpari - live - 818497"       : { $serverDirectory = 'AlpariUK-Micro-2'     ; $account =    818497; break; }
   case "alpari - live - 860960"       : { $serverDirectory = 'AlpariUK-Micro-1'     ; $account =    860960; break; }
   case "apbg - demo - 1106906"        : { $serverDirectory = 'APBGTrading-Server'   ; $account =   1106906; break; }
   case "atc - demo - 8199"            : { $serverDirectory = 'ATCBrokers-Demo'      ; $account =      8199; break; }
   case "atc - live - 21529"           : { $serverDirectory = 'ATCBrokers-Live'      ; $account =     21529; break; }
   case "fb capital - demo - 9947"     : { $serverDirectory = 'ForexBaltic-Server'   ; $account =      9947; break; }
   case "mb trading - demo - 500452864": { $serverDirectory = 'MBTrading-Demo Server'; $account = 500452864; break; }
   case "sig - live - 52464"           : { $serverDirectory = 'SIG-Real.com'         ; $account =     52464; break; }
   case "forex ltd - demo - 444868"    : { $serverDirectory = 'FOREX-Server'         ; $account =    444868; break; }
   default:
      exit("\n  Syntax: mt4WriteBalanceHst <history-file.csv>\n");
}
$serverDirectory = $parts[0].DIRECTORY_SEPARATOR.'history'.DIRECTORY_SEPARATOR.$serverDirectory;


// Quelldaten einlesen
$csvHistory = array();

$lines = file($sourceFile);
$headerChecked = false;
foreach ($lines as $i => &$line) {
   if ($line{0}=='#')                                          // Kommentare überspringen
      continue;
   if (!$headerChecked) {                                      // Header in 1. Datenzeile prüfen
      if (trim($line) != "Ticket\tOpenTime\tOpenTimestamp\tDescription\tType\tSize\tSymbol\tOpenPrice\tStopLoss\tTakeProfit\tCloseTime\tCloseTimestamp\tClosePrice\tExpirationTime\tExpirationTimestamp\tMagicNumber\tCommission\tSwap\tNetProfit\tGrossProfit\tBalance\tComment")
         exit("Invalid file format (unexpected data header in line ".($i+1).")\n");
      $headerChecked = true;
      continue;
   }
   $values = explode("\t", $line);                             // Spaltenanzahl prüfen
   if (sizeOf($values) != 22)
      exit("Invalid file format (unexpected number of columns in line ".($i+1).")\n");

                      // ServerTime , FxTime, Balance
   $csvHistory[] = array($values[11], null  , $values[20]);    // alles Strings
}


// existierende HST-M1-Datei öffnen bzw. neu anlegen
$hstFile = $serverDirectory.DIRECTORY_SEPARATOR.$account.'.BA'.PERIOD_M1.'.hst';
$hFile = fOpen($hstFile, 'ab+');

$fileSize = fileSize($hstFile);
echoPre($fileSize);

fClose($hFile);
unlink($hstFile);
?>
