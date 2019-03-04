<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


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
    public function getHistory($timeframe, $time) {
        if (!is_int($timeframe))     throw new IllegalTypeException('Illegal type of parameter $timeframe: '.gettype($timeframe));
        if (!is_int($time))          throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if ($timeframe != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($timeframe).') not implemented');

        if (!$symbols = $this->loadComponents(first($this->components)))
            return [];
        if (!$time && !($time = $this->findCommonHistoryStartM1($symbols)))     // if no time was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($time))                                // skip non-trading days
            return [];
        if (!$quotes = $this->loadComponentHistory($symbols, $time))
            return [];

        // calculate quotes
        echoPre('[Info]    '.str_pad($this->symbolName, 6).'  calculating M1 history for '.gmdate('D, d-M-Y', $time));
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
