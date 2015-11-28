<?php
/**
 * Globale Konstanten.
 */

// Timeframe-Identifier
define('PERIOD_M1' ,      1);    // 1 minute
define('PERIOD_M5' ,      5);    // 5 minutes
define('PERIOD_M15',     15);    // 15 minutes
define('PERIOD_M30',     30);    // 30 minutes
define('PERIOD_H1' ,     60);    // 1 hour
define('PERIOD_H4' ,    240);    // 4 hours
define('PERIOD_D1' ,   1440);    // daily
define('PERIOD_W1' ,  10080);    // weekly
define('PERIOD_MN1',  43200);    // monthly
define('PERIOD_Q1' , 129600);    // a quarter (3 months)


// Operation-Types
define('OP_BUY'      ,   0);     //    MT4: long position
define('OP_SELL'     ,   1);     //         short position
define('OP_BUYLIMIT' ,   2);     //         buy limit order
define('OP_SELLLIMIT',   3);     //         sell limit order
define('OP_BUYSTOP'  ,   4);     //         stop buy order
define('OP_SELLSTOP' ,   5);     //         stop sell order
define('OP_BALANCE'  ,   6);     //         account credit or withdrawal transaction
define('OP_CREDIT'   ,   7);     //         credit facility, no transaction
define('OP_TRANSFER' ,   8);     // custom: Balance-Änderung durch Kunden (Deposit/Withdrawal)
define('OP_VENDOR'   ,   9);     //         Balance-Änderung durch Criminal (Dividende, Swap, Ausgleich etc.)


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


// Struct-Sizes
define('DUKASCOPY_BAR_SIZE'  ,  24);
define('HISTORY_HEADER_SIZE' , 148);
define('HISTORY_BAR_400_SIZE',  44);
define('HISTORY_BAR_401_SIZE',  60);
define('MYFX_BAR_SIZE'       ,  24);


// SimpleTrader: Indizes der von preg_match_all() zurückgegebenen OpenPosition-Datenarrays
define('I_STOP_TAKEPROFIT',  1);
define('I_STOP_STOPLOSS'  ,  2);
define('I_STOP_OPENTIME'  ,  3);
define('I_STOP_OPENPRICE' ,  4);
define('I_STOP_LOTSIZE'   ,  5);
define('I_STOP_TYPE'      ,  6);
define('I_STOP_SYMBOL'    ,  7);
define('I_STOP_PROFIT'    ,  8);
define('I_STOP_PIPS'      ,  9);
define('I_STOP_COMMENT'   , 10);


// SimpleTrader: Indizes der von preg_match_all() zurückgegebenen ClosedPosition-Datenarrays
define('I_STH_TAKEPROFIT' ,  1);
define('I_STH_STOPLOSS'   ,  2);
define('I_STH_OPENTIME'   ,  3);
define('I_STH_CLOSETIME'  ,  4);
define('I_STH_OPENPRICE'  ,  5);
define('I_STH_CLOSEPRICE' ,  6);
define('I_STH_LOTSIZE'    ,  7);
define('I_STH_TYPE'       ,  8);
define('I_STH_SYMBOL'     ,  9);
define('I_STH_PROFIT'     , 10);
define('I_STH_PIPS'       , 11);
define('I_STH_COMMENT'    , 13);
