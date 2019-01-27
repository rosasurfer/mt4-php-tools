<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * USDLFX synthesizer
 *
 * A {@link Synthesizer} for calculating the "LiteForex US Dollar index" (a scaled-down FX6 index).
 *
 * <pre>
 * Formula:
 * --------
 * USDLFX = \sqrt[7]{\frac{USDCAD * USDCHF * USDJPY}{AUDUSD * EURUSD * GBPUSD}}
 * </pre>
 */
class USDLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'pairs' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        if (!$symbols = $this->loadSymbols(first($this->components)))
            return [];
        if (!$day && !($day = $this->findCommonHistoryM1Start($symbols)))   // if no day was specified look-up the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($day))                             // skip non-trading days
            return [];
        if (!$quotes = $this->loadHistory($symbols, $day))
            return [];

        // calculate quotes
        echoPre('[Info]    '.$this->symbolName.'  calculating M1 history for '.gmDate('D, d-M-Y', $day));
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
            $bars[$i]['high' ] = $iOpen > $iClose ? $open : $close;     // no min()/max() for performance
            $bars[$i]['low'  ] = $iOpen < $iClose ? $open : $close;
            $bars[$i]['close'] = $close;
            $bars[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $bars;
    }
}
