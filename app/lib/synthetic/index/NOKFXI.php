<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;
use rosasurfer\rt\lib\synthetic\ISynthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * NOKFXI synthesizer
 *
 * A {@link ISynthesizer} for calculating the Norwegian Krone currency index. Due to the Krone's low value the index is
 * scaled-up by a factor of 10. This adjustment only effects the nominal scala, not the shape of the NOK index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * NOKFXI = 10 * USDLFX / USDNOK
 * NOKFXI = 10 * pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7) / USDNOK
 * NOKFXI = 10 * pow(NOKJPY / (AUDNOK * CADNOK * CHFNOK * EURNOK * GBPNOK * USDNOK), 1/7)
 * </pre>
 */
class NOKFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDLFX', 'USDNOK'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDNOK'],
        'crosses' => ['AUDNOK', 'CADNOK', 'CHFNOK', 'EURNOK', 'GBPNOK', 'NOKJPY', 'USDNOK'],
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
        $USDNOK = $quotes['USDNOK'];
        $USDLFX = $quotes['USDLFX'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // NOKFXI = 10 * USDLFX / USDNOK
        foreach ($USDNOK as $i => $bar) {
            $usdnok = $USDNOK[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = 10 * $usdlfx / $usdnok;
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdnok = $USDNOK[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = 10 * $usdlfx / $usdnok;
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
