<?php
/**
 * Globale Konstanten.
 */
define('PROJECT_DIRECTORY', realPath(dirName(__FILE__).'/../../..'));


// Timeframe-Identifier
define('PERIOD_M1' ,     1);     // 1 minute
define('PERIOD_M5' ,     5);     // 5 minutes
define('PERIOD_M15',    15);     // 15 minutes
define('PERIOD_M30',    30);     // 30 minutes
define('PERIOD_H1' ,    60);     // 1 hour
define('PERIOD_H4' ,   240);     // 4 hours
define('PERIOD_D1' ,  1440);     // daily
define('PERIOD_W1' , 10080);     // weekly
define('PERIOD_MN1', 43200);     // monthly


// Operation-Types
define('OP_BUY'      ,   0);     //    MT4: long position
define('OP_SELL'     ,   1);     //         short position
define('OP_BUYLIMIT' ,   2);     //         buy limit order
define('OP_SELLLIMIT',   3);     //         sell limit order
define('OP_BUYSTOP'  ,   4);     //         stop buy order
define('OP_SELLSTOP' ,   5);     //         stop sell order
define('OP_BALANCE'  ,   6);     //         account credit or withdrawel transaction
define('OP_CREDIT'   ,   7);     //         credit facility, no transaction
define('OP_TRANSFER' ,   8);     // custom: Balance-Änderung durch Kunden (Ein-/Auszahlung)
define('OP_VENDOR'   ,   9);     //         Balance-Änderung durch Criminal (Swap, sonstiges)


// Spalten der internen History-Daten in UploadAccountHistoryForm
define('AH_TICKET'     ,  0);
define('AH_OPENTIME'   ,  1);
define('AH_TYPE'       ,  2);
define('AH_UNITS'      ,  3);
define('AH_SYMBOL'     ,  4);
define('AH_OPENPRICE'  ,  5);
define('AH_CLOSETIME'  ,  6);
define('AH_CLOSEPRICE' ,  7);
define('AH_COMMISSION' ,  8);
define('AH_SWAP'       ,  9);
define('AH_PROFIT'     , 10);
define('AH_MAGICNUMBER', 11);
define('AH_COMMENT'    , 12);
?>
