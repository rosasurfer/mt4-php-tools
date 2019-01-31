<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * JPYFX7 synthesizer
 *
 * A {@link Synthesizer} for calculating the Japanese Yen FX7 index. Due to the Yen's low value the index is scaled-up by a
 * factor of 100. This adjustment only effects the nominal scala, not the shape of the JPY index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * JPYFX7 = 100 * USDFX7 / pow(USDJPY, 8/7)
 * JPYFX7 = 100 * pow(USDCAD * USDCHF / (AUDUSD * EURUSD * GBPUSD * NZDUSD), 1/7) / USDJPY;
 * JPYFX7 = 100 * pow(1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * NZDJPY * USDJPY), 1/7)
 * </pre>
 */
class JPYFX7 extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDFX7', 'USDJPY'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'NZDUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['AUDJPY', 'CADJPY', 'CHFJPY', 'EURJPY', 'GBPJPY', 'NZDJPY', 'USDJPY'],
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
        $USDFX7 = $quotes['USDFX7'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // JPYFX7 = 100 * USDFX7 / pow(USDJPY, 8/7)
        foreach ($USDJPY as $i => $bar) {
            $usdjpy = $USDJPY[$i]['open'];
            $usdfx7 = $USDFX7[$i]['open'];
            $open   = 100 * $usdfx7 / pow($usdjpy, 8/7);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdjpy = $USDJPY[$i]['close'];
            $usdfx7 = $USDFX7[$i]['close'];
            $close  = 100 * $usdfx7 / pow($usdjpy, 8/7);
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
