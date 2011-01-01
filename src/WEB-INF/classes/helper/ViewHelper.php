<?php
/**
 * ViewHelper
 */
class ViewHelper extends StaticClass {


   // MT4 operation types
   public static /*string[]*/ $operationTypes = array(OP_BUY       => 'Buy'       ,
                                                      OP_SELL      => 'Sell'      ,
                                                      OP_BUYLIMIT  => 'Buy Limit' ,
                                                      OP_SELLLIMIT => 'Sell Limit',
                                                      OP_BUYSTOP   => 'Stop Buy'  ,
                                                      OP_SELLSTOP  => 'Stop Sell' ,
                                                      OP_BALANCE   => 'Balance'   ,
                                                      OP_CREDIT    => 'Credit'    );

   // instruments and their names
   public static /*string[]*/ $instruments = array('AUDCAD' => 'AUD/CAD',
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
                                                   'USDZAR' => 'USD/ZAR');
}
?>
