<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\core\Object;


/**
 * EURX calculator
 *
 * A calculator for calculating the "ICE Euro Futures index".
 */
class EURX extends Object implements CalculatorInterface {


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
