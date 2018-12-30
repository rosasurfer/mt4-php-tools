<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\core\Object;
use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rost\model\RosaSymbol;


/**
 * USDLFX calculator
 *
 * A calculator for calculating the "LiteForex US Dollar index".
 *
 * Formula: USDLFX = \sqrt[7]{\frac{USDCAD * USDCHF * USDJPY}{AUDUSD * EURUSD * GBPUSD}}
 */
class USDLFX extends Object implements CalculatorInterface {


    /** @var string[] */
    private $components = ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'];


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
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        if (!$day) {
            // resolve the oldest available history for all components
            foreach ($this->components as $name) {
                /** @var RosaSymbol $symbol */
                $symbol = RosaSymbol::dao()->getByName($name);
                $historyStart = (int) $symbol->getHistoryStartM1('U');      // 00:00 FXT of the first stored day
                echoPre('[Info]    '.$symbol->getName().'  M1 history starts: '.($historyStart ? gmDate('Y-m-d H:i:s', $historyStart) : 0));
                if (!$historyStart) return [];                              // no history stored
            }
        }
        else {
            // check available history for the specified day
        }

        return [];
    }
}
