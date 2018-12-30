<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\core\Object;


/**
 * USDLFX calculator
 *
 * A calculator for calculating the "LiteForex US Dollar index".
 */
class USDLFX extends Object implements CalculatorInterface {


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTicks($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        return [];
    }
}
