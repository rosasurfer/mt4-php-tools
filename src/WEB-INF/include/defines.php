<?php
/**
 * Globale Konstanten.
 */

// Wochentage
define('SUNDAY'   , 0);
define('MONDAY'   , 1);
define('TUESDAY'  , 2);
define('WEDNESDAY', 3);
define('THURSDAY' , 4);
define('FRIDAY'   , 5);
define('SATURDAY' , 6);


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
define('OP_BUY'      , 0);       // long position
define('OP_SELL'     , 1);       // short position
define('OP_BUYLIMIT' , 2);       // buy limit order
define('OP_SELLLIMIT', 3);       // sell limit order
define('OP_BUYSTOP'  , 4);       // stop buy order
define('OP_SELLSTOP' , 5);       // stop sell order
define('OP_BALANCE'  , 6);       // account credit or withdrawel transaction
define('OP_CREDIT'   , 7);       // credit facility, no transaction
?>
