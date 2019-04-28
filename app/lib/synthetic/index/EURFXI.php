<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\ISynthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * EURFXI synthesizer
 *
 * A {@link ISynthesizer} for calculating the Euro currency index.
 *
 * <pre>
 * Formulas:
 * ---------
 * EURFXI = pow(USDLFX * EURUSD, 7/6)
 * EURFXI = pow(USDCAD * USDCHF * USDJPY / (AUDUSD * GBPUSD), 1/6) * EURUSD
 * EURFXI = pow(EURAUD * EURCAD * EURCHF * EURGBP * EURJPY * EURUSD, 1/6)
 * </pre>
 */
class EURFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['EURUSD', 'USDLFX'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['EURAUD', 'EURCAD', 'EURCHF', 'EURGBP', 'EURJPY', 'EURUSD'],
    ];


    /**
     * {@inheritdoc}
     */
    public function calculateHistory($period, $time, $optimized = false) {
        if (!is_int($period))     throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        if (!is_int($time))       throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if ($optimized)           echoPre('[Warn]    '.str_pad($this->symbolName, 6).'::'.__FUNCTION__.'($optimized=TRUE)  skipping unimplemented feature');

        if (!$symbols = $this->getComponents(first($this->components)))
            return [];
        if (!$time && !($time=$this->findCommonHistoryStartM1($symbols)))       // if no time was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($time))                                // skip non-trading days
            return [];
        if (!$quotes = $this->getComponentsHistory($symbols, $time))
            return [];

        // calculate quotes
        echoPre('[Info]    '.str_pad($this->symbolName, 6).'  calculating M1 history for '.gmdate('D, d-M-Y', $time));
        $EURUSD = $quotes['EURUSD'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // EURFXI = pow(USDLFX * EURUSD, 7/6)
        foreach ($EURUSD as $i => $bar) {
            $eurusd = $EURUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = pow($usdlfx * $eurusd, 7/6);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $eurusd = $EURUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = pow($usdlfx * $eurusd, 7/6);
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
