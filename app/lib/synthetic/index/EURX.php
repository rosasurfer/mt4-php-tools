<?php
namespace rosasurfer\rt\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\synthetic\AbstractSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;


/**
 * EURX synthesizer
 *
 * A {@link Synthesizer} for calculating the ICE Euro index.
 *
 * <pre>
 * Formula:
 * --------
 * EURX = 34.38805726 * EURUSD * pow(USDCHF, 0.1113) * pow(USDJPY, 0.1891) * pow(USDSEK, 0.0785) / pow(GBPUSD, 0.3056)
 * EURX = 34.38805726 * pow(EURCHF, 0.1113) * pow(EURGBP, 0.3056) * pow(EURJPY, 0.1891) * pow(EURSEK, 0.0785) * pow(EURUSD, 0.3155)
 * </pre>
 */
class EURX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'majors'  => ['EURUSD', 'GBPUSD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'crosses' => ['EURCHF', 'EURGBP', 'EURJPY', 'EURSEK', 'EURUSD'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$day && !($day = $this->findCommonHistoryM1Start($symbols)))   // if no day was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($day))                             // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $day))
            return [];

        // calculate quotes
        echoPre('[Info]    '.$this->symbolName.'  calculating M1 history for '.gmdate('D, d-M-Y', $day));
        $EURUSD = $quotes['EURUSD'];
        $GBPUSD = $quotes['GBPUSD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];
        $USDSEK = $quotes['USDSEK'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // EURX = 34.38805726 * EURUSD * pow(USDCHF, 0.1113) * pow(USDJPY, 0.1891) * pow(USDSEK, 0.0785) / pow(GBPUSD, 0.3056)
        foreach ($EURUSD as $i => $bar) {
            $eurusd = $EURUSD[$i]['open'];
            $gbpusd = $GBPUSD[$i]['open'];
            $usdchf = $USDCHF[$i]['open'];
            $usdjpy = $USDJPY[$i]['open'];
            $usdsek = $USDSEK[$i]['open'];
            $open   = 34.38805726 * $eurusd * pow($usdchf, 0.1113) * pow($usdjpy, 0.1891) * pow($usdsek, 0.0785) / pow($gbpusd, 0.3056);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $eurusd = $EURUSD[$i]['close'];
            $gbpusd = $GBPUSD[$i]['close'];
            $usdchf = $USDCHF[$i]['close'];
            $usdjpy = $USDJPY[$i]['close'];
            $usdsek = $USDSEK[$i]['close'];
            $close  = 34.38805726 * $eurusd * pow($usdchf, 0.1113) * pow($usdjpy, 0.1891) * pow($usdsek, 0.0785) / pow($gbpusd, 0.3056);
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
