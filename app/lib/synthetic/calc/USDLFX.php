<?php
namespace rosasurfer\rost\synthetic\calc;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rost\FXT;
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
    public function calculateQuotes($fxDay) {
        if (!is_int($fxDay)) throw new IllegalTypeException('Illegal type of parameter $fxDay: '.getType($fxDay));

        /** @var RosaSymbol $usdlfx */
        $usdlfx = $this->symbol;
        /** @var RosaSymbol $components[] */
        $components = [];

        foreach ($this->components as $name) {
            /** @var RosaSymbol $symbol */
            $symbol = RosaSymbol::dao()->getByName($name);
            $components[$symbol->getName()] = $symbol;
        }

        if (!$fxDay) {
            // resolve the oldest available history of all components
            /** @var RosaSymbol $pair */
            foreach ($components as $pair) {
                $historyStart = (int) $pair->getHistoryM1Start('U');    // 00:00 FXT of the first stored day
                if (!$historyStart) return [];                          // no history stored
                $fxDay = max($fxDay, $historyStart);
            }
            echoPre('[Info]    '.$usdlfx->getName().'  common M1 history starts at '.FXT::fxDate('D, d-M-Y', $fxDay));
        }
        if (!$usdlfx->isTradingDay($fxDay))                             // skip non-trading days
            return [];

        // load history for the specified day
        $quotes = [];
        foreach ($components as $name => $pair) {
            $quotes[$name] = $pair->getHistoryM1($fxDay);
            echoPre($quotes[$name][0]);
        }
        return [];
    }
}
