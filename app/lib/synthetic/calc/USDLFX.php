<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\rost\model\RosaSymbol;


/**
 * USDLFX calculator
 *
 * A calculator for calculating the "LiteForex US Dollar index".
 *
 * Formula: USDLFX = \sqrt[7]{\frac{USDCAD * USDCHF * USDJPY}{AUDUSD * EURUSD * GBPUSD}}
 */
class USDLFX extends Calculator {


    /** @var string[] */
    protected $components = ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        /** @var RosaSymbol $usdlfx */
        $usdlfx = $this->symbol;
        /** @var RosaSymbol $components[] */
        $components = [];

        foreach ($this->components as $name) {
            $symbol = RosaSymbol::dao()->getByName($name);
            $components[$symbol->getName()] = $symbol;
        }

        if (!$day) {
            // resolve the oldest available history of all components
            /** @var RosaSymbol $pair */
            foreach ($components as $pair) {
                $historyStart = (int) $pair->getHistoryStartM1('U');    // 00:00 FXT of the first stored day
                if (!$historyStart) return [];                          // no history stored
                $day = max($day, $historyStart);
            }
            echoPre('[Info]    '.$usdlfx->getName().'  common M1 history starts at '.gmDate('D, d-M-Y', $day));
        }
        if (!$usdlfx->isTradingDay($day))                               // skip non-trading days
            return [];

        // load history for the specified day
        $quotes = [];
        /** @var RosaSymbol $pair */
        foreach ($components as $name => $pair) {
            $quotes[$name] = $pair->getM1History($day);
        }

        return [];
    }
}
