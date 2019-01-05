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

        $pairs = [];
        foreach ($this->components as $name) {
            /** @var RosaSymbol $pair */
            $pair = RosaSymbol::dao()->getByName($name);
            $pairs[$pair->getName()] = $pair;
        }

        // on $fxDay == 0 start with the oldest available history of all components
        if (!$fxDay) {
            /** @var RosaSymbol $pair */
            foreach ($pairs as $pair) {
                $historyStart = (int) $pair->getHistoryM1Start('U');    // 00:00 FXT of the first stored day
                if (!$historyStart) {
                    echoPre('[Error]   '.$this->symbol->getName().'  M1 history for '.$pair->getName().' not available');
                    return [];                                          // no history stored
                }
                $fxDay = max($fxDay, $historyStart);
            }
            echoPre('[Info]    '.$this->symbol->getName().'  common M1 history starts at '.FXT::fxDate('D, d-M-Y', $fxDay));
        }
        if (!$this->symbol->isTradingDay($fxDay)) {                     // skip non-trading days
            echoPre('[Debug]   '.$this->symbol->getName().'  skipping non-trading day: '.gmDate('D, d-M-Y', $day));
            return [];
        }

        // load history for the specified day
        $quotes = [];
        foreach ($pairs as $name => $pair) {
            $quotes[$name] = $pair->getHistoryM1($fxDay);
        }

        // calculate quotes
        echoPre('[Info]    '.$this->symbol->getName().'  calculating M1 history for '.gmDate('D, d-M-Y', $fxDay));
        $AUDUSD = $quotes['AUDUSD'];
        $EURUSD = $quotes['EURUSD'];
        $GBPUSD = $quotes['GBPUSD'];
        $USDCAD = $quotes['USDCAD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

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

            $bars[$i]['time' ] = $bar['time'];
            $bars[$i]['open' ] = $open;
            $bars[$i]['high' ] = $iOpen > $iClose ? $open : $close;                 // no min()/max(): This is a massive loop and
            $bars[$i]['low'  ] = $iOpen < $iClose ? $open : $close;                 // every single function call slows it down.
            $bars[$i]['close'] = $close;
            $bars[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $bars;
    }
}
