<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * CHFFX7 synthesizer
 *
 * A {@link Synthesizer} for calculating the Swiss Franc FX7 index.
 *
 * <pre>
 * Formulas:
 * ---------
 * CHFFX7 = USDFX7 / pow(USDCHF, 8/7)
 * CHFFX7 = pow(USDCAD * USDJPY / (AUDUSD * EURUSD * GBPUSD * NZDUSD), 1/7) / USDCHF
 * CHFFX7 = pow(CHFJPY / (AUDCHF * CADCHF * EURCHF * GBPCHF * NZDCHF * USDCHF), 1/7)
 * </pre>
 */
class CHFFX7 extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDCHF', 'USDFX7'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'NZDUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['AUDCHF', 'CADCHF', 'CHFJPY', 'EURCHF', 'GBPCHF', 'NZDCHF', 'USDCHF'],
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
        $USDCHF = $quotes['USDCHF'];
        $USDFX7 = $quotes['USDFX7'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // CHFFX7 = USDFX7 / pow(USDCHF, 8/7)
        foreach ($USDCHF as $i => $bar) {
            $usdchf = $USDCHF[$i]['open'];
            $usdfx7 = $USDFX7[$i]['open'];
            $open   = $usdfx7 / pow($usdchf, 8/7);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdchf = $USDCHF[$i]['close'];
            $usdfx7 = $USDFX7[$i]['close'];
            $close  = $usdfx7 / pow($usdchf, 8/7);
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
