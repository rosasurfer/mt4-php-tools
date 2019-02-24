<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;


/**
 * JPYFXI synthesizer
 *
 * A {@link Synthesizer} for calculating the Japanese Yen currency index. Due to the Yen's low value the index is scaled-up
 * by a factor of 100. This adjustment only effects the nominal scala, not the shape of the JPY index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * JPYFXI = 100 / pow(USDLFX / USDJPY, 7/6)
 * JPYFXI = 100 * pow(USDCAD * USDCHF / (AUDUSD * EURUSD * GBPUSD), 1/6) * USDJPY
 * JPYFXI = 100 * pow(1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * USDJPY), 1/6)
 * </pre>
 */
class JPYFXI extends AbstractSynthesizer {


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
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$day && !($day = $this->findCommonHistoryStartM1($symbols)))   // if no day was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($day))                             // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $day))
            return [];

        // calculate quotes
        echoPre('[Info]    '.$this->symbolName.'  calculating M1 history for '.gmdate('D, d-M-Y', $day));
        $USDJPY = $quotes['USDJPY'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // JPYFXI = 100 / pow(USDLFX / USDJPY, 7/6)
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
