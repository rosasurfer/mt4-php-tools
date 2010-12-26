<?
/**
 * ViewHelper
 */
class ViewHelper extends StaticClass {


   // MT4 operation types
   public static $operationTypes = array(OP_BUY       => 'Buy',
                                         OP_SELL      => 'Sell',
                                         OP_BUYLIMIT  => 'Buy Limit',
                                         OP_SELLLIMIT => 'Sell Limit',
                                         OP_BUYSTOP   => 'Stop Buy',
                                         OP_SELLSTOP  => 'Stop Sell',
                                         OP_BALANCE   => 'Balance',
                                         OP_CREDIT    => 'Credit');
}
?>
