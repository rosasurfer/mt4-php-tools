<?php
namespace rosasurfer\xtrade\view;

use rosasurfer\core\StaticClass;


/**
 * ViewHelper
 */
class ViewHelper extends StaticClass {


    // operation types
    public static /*string[]*/ $operationTypes = [
        OP_BUY       => 'buy'       ,   // 0
        OP_SELL      => 'sell'      ,   // 1
        OP_BUYLIMIT  => 'buy limit' ,   // 2
        OP_SELLLIMIT => 'sell limit',   // 3
        OP_BUYSTOP   => 'stop buy'  ,   // 4
        OP_SELLSTOP  => 'stop sell' ,   // 5
        OP_BALANCE   => 'balance'   ,   // 6
        OP_CREDIT    => 'credit'    ,   // 7
        OP_TRANSFER  => 'transfer'  ,   // 8
        OP_VENDOR    => 'vendor'    ,   // 9
    ];

    // instruments and their names
    public static /*string[]*/ $instruments = [
        'AUDCAD' => 'AUD/CAD',
        'AUDCHF' => 'AUD/CHF',
        'AUDJPY' => 'AUD/JPY',
        'AUDNZD' => 'AUD/NZD',
        'AUDUSD' => 'AUD/USD',
        'CADCHF' => 'CAD/CHF',
        'CADJPY' => 'CAD/JPY',
        'CHFJPY' => 'CHF/JPY',
        'EURAUD' => 'EUR/AUD',
        'EURCAD' => 'EUR/CAD',
        'EURCHF' => 'EUR/CHF',
        'EURDKK' => 'EUR/DKK',
        'EURGBP' => 'EUR/GBP',
        'EURJPY' => 'EUR/JPY',
        'EURNOK' => 'EUR/NOK',
        'EURNZD' => 'EUR/NZD',
        'EURRUR' => 'EUR/RUR',
        'EURSEK' => 'EUR/SEK',
        'EURUSD' => 'EUR/USD',
        'GBPAUD' => 'GBP/AUD',
        'GBPCAD' => 'GBP/CAD',
        'GBPCHF' => 'GBP/CHF',
        'GBPJPY' => 'GBP/JPY',
        'GBPNZD' => 'GBP/NZD',
        'GBPRUR' => 'GBP/RUR',
        'GBPUSD' => 'GBP/USD',
        'NZDCAD' => 'NZD/CAD',
        'NZDCHF' => 'NZD/CHF',
        'NZDJPY' => 'NZD/JPY',
        'NZDUSD' => 'NZD/USD',
        'SGDJPY' => 'SGD/JPY',
        'USDCAD' => 'USD/CAD',
        'USDCHF' => 'USD/CHF',
        'USDCZK' => 'USD/CZK',
        'USDDKK' => 'USD/DKK',
        'USDHKD' => 'USD/HKD',
        'USDHUF' => 'USD/HUF',
        'USDJPY' => 'USD/JPY',
        'USDMXN' => 'USD/MXN',
        'USDNOK' => 'USD/NOK',
        'USDPLN' => 'USD/PLN',
        'USDRUR' => 'USD/RUR',
        'USDSEK' => 'USD/SEK',
        'USDSGD' => 'USD/SGD',
        'USDZAR' => 'USD/ZAR',
    ];
}
