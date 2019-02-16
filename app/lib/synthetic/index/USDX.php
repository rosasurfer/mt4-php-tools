<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;


/**
 * USDX synthesizer
 *
 * A {@link Synthesizer} for calculating the ICE US-Dollar index.
 *
 * <pre>
 * Formula:
 * --------
 * USDX = 50.14348112 * pow(USDCAD, 0.091) * pow(USDCHF, 0.036) * pow(USDJPY, 0.136) * pow(USDSEK, 0.042) / (pow(EURUSD, 0.576) * pow(GBPUSD, 0.119))
 * </pre>
 */
class USDX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'crosses' => ['EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK'],
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
        $USDCAD = $quotes['USDCAD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];
        $USDSEK = $quotes['USDSEK'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // USDX = 50.14348112 * pow(USDCAD, 0.091) * pow(USDCHF, 0.036) * pow(USDJPY, 0.136) * pow(USDSEK, 0.042) / (pow(EURUSD, 0.576) * pow(GBPUSD, 0.119))
        foreach ($EURUSD as $i => $bar) {
            $eurusd = $EURUSD[$i]['open'];
            $gbpusd = $GBPUSD[$i]['open'];
            $usdcad = $USDCAD[$i]['open'];
            $usdchf = $USDCHF[$i]['open'];
            $usdjpy = $USDJPY[$i]['open'];
            $usdsek = $USDSEK[$i]['open'];
            $open   = 50.14348112 * pow($usdcad, 0.091) * pow($usdchf, 0.036) * pow($usdjpy, 0.136) * pow($usdsek, 0.042) / (pow($eurusd, 0.576) * pow($gbpusd, 0.119));
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $eurusd = $EURUSD[$i]['close'];
            $gbpusd = $GBPUSD[$i]['close'];
            $usdcad = $USDCAD[$i]['close'];
            $usdchf = $USDCHF[$i]['close'];
            $usdjpy = $USDJPY[$i]['close'];
            $usdsek = $USDSEK[$i]['close'];
            $close  = 50.14348112 * pow($usdcad, 0.091) * pow($usdchf, 0.036) * pow($usdjpy, 0.136) * pow($usdsek, 0.042) / (pow($eurusd, 0.576) * pow($gbpusd, 0.119));
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
