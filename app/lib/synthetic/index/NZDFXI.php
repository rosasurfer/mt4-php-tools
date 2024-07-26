<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * NZDFXI synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic New Zealand Dollar currency index.
 *
 * <pre>
 * Formulas:
 * ---------
 * NZDFXI = USDLFX * NZDUSD
 * NZDFXI = pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7) * NZDUSD
 * NZDFXI = pow(NZDCAD * NZDCHF * NZDJPY * NZDUSD / (AUDNZD * EURNZD * GBPNZD), 1/7)
 * </pre>
 */
class NZDFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['NZDUSD', 'USDLFX'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'NZDUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['AUDNZD', 'EURNZD', 'GBPNZD', 'NZDCAD', 'NZDCHF', 'NZDJPY', 'NZDUSD'],
    ];


    /**
     *
     */
    public function calculateHistory($period, $time) {
        Assert::int($period, '$period');
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        Assert::int($time, '$time');

        if (!$symbols = $this->getComponents(first($this->components)))
            return [];
        if (!$time && !($time=$this->findCommonHistoryStartM1($symbols)))       // if no time was specified find the oldest available history
            return [];
        if (!$this->symbol->isTradingDay($time))                                // skip non-trading days
            return [];
        if (!$quotes = $this->getComponentsHistory($symbols, $time))
            return [];

        Output::out('[Info]    '.str_pad($this->symbolName, 6).'  calculating M1 history for '.gmdate('D, d-M-Y', $time));

        // calculate quotes
        $NZDUSD = $quotes['NZDUSD'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // NZDFXI = USDLFX * NZDUSD
        foreach ($NZDUSD as $i => $bar) {
            $nzdusd = $NZDUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx * $nzdusd, $digits);
            $iOpen  = (int) round($open/$point);

            $nzdusd = $NZDUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx * $nzdusd, $digits);
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
