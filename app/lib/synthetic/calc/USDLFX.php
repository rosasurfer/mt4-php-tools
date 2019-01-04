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

        // on $fxDay == 0 start with the oldest available history of all components
        if (!$fxDay) {
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
        }

        // calculate the USDLFX
        $AUDUSD = $quotes['AUDUSD'];
        $EURUSD = $quotes['EURUSD'];
        $GBPUSD = $quotes['GBPUSD'];
        $USDCAD = $quotes['USDCAD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];

        $digits = $usdlfx->getDigits();
        $point  = $usdlfx->getPoint();
        $result = [];

        // USDLFX = \sqrt[7]{\frac{USDCAD * USDCHF * USDJPY}{AUDUSD * EURUSD * GBPUSD}}
        foreach ($AUDUSD as $i => $bar) {
            $audusd = $AUDUSD[$i]['open'];
            $eurusd = $EURUSD[$i]['open'];
            $gbpusd = $GBPUSD[$i]['open'];
            $usdcad = $USDCAD[$i]['open'];
            $usdchf = $USDCHF[$i]['open'];
            $usdjpy = $USDJPY[$i]['open'];
            $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd), 1/7);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $audusd = $AUDUSD[$i]['close'];
            $eurusd = $EURUSD[$i]['close'];
            $gbpusd = $GBPUSD[$i]['close'];
            $usdcad = $USDCAD[$i]['close'];
            $usdchf = $USDCHF[$i]['close'];
            $usdjpy = $USDJPY[$i]['close'];
            $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd), 1/7);
            $close  = round($close, $digits);
            $iClose = (int) round($close/$point);

            $result[$i]['time' ] = $bar['time'];
            $result[$i]['open' ] = $open;
            $result[$i]['high' ] = $iOpen > $iClose ? $open : $close;                // min()/max() do not perform very well
            $result[$i]['low'  ] = $iOpen < $iClose ? $open : $close;
            $result[$i]['close'] = $close;
            $result[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $result;
    }
}
