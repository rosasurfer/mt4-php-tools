<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * JPYFX6 synthesizer
 *
 * A {@link Synthesizer} for calculating the Japanese Yen FX6 index. Due to the Yen's extreme devaluation the index is
 * scaled-up by a factor of 100. This adjustment only effects the nominal scala, not the shape of the Yen index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * JPYFX6 = 100 / pow(USDLFX / USDJPY, 7/6)
 * JPYFX6 = 100 * pow(USDCAD * USDCHF / (AUDUSD * EURUSD * GBPUSD), 1/6) * USDJPY
 * JPYFX6 = 100 * pow(1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * USDJPY), 1/6)
 * </pre>
 */
class JPYFX6 extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDJPY', 'USDLFX'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['AUDJPY', 'CADJPY', 'CHFJPY', 'EURJPY', 'GBPJPY', 'USDJPY'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$day && !($day = $this->findCommonHistoryM1Start($symbols)))   // if no day was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($day))                             // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $day))
            return [];

        // calculate quotes
        echoPre('[Info]    '.$this->symbolName.'  calculating M1 history for '.gmDate('D, d-M-Y', $day));
        $USDJPY = $quotes['USDJPY'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // JPYFX6 = 100 / pow(USDLFX / USDJPY, 7/6)
        foreach ($USDJPY as $i => $bar) {
            $usdjpy = $USDJPY[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = 100 / pow($usdlfx / $usdjpy, 7/6);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdjpy = $USDJPY[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = 100 / pow($usdlfx / $usdjpy, 7/6);
            $close  = round($close, $digits);
            $iClose = (int) round($close/$point);

            $bars[$i]['time' ] = $bar['time'];
            $bars[$i]['open' ] = $open;
            $bars[$i]['high' ] = $iOpen > $iClose ? $open : $close;         // no min()/max() for performance
            $bars[$i]['low'  ] = $iOpen < $iClose ? $open : $close;
            $bars[$i]['close'] = $close;
            $bars[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $bars;
    }
}
