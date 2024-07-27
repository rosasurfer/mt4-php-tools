<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;

use function rosasurfer\ministruts\first;
use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * XAUI synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic Gold index.
 *
 * <pre>
 * Formulas:
 * ---------
 * XAUI = USDLFX * XAUUSD
 * XAUI = pow(XAUAUD * XAUCAD * XAUCHF * XAUEUR * XAUUSD * XAUGBP * XAUJPY, 1/7)
 * </pre>
 */
class XAUI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['XAUUSD', 'USDLFX'],
        'crosses' => ['XAUAUD', 'XAUCAD', 'XAUCHF', 'XAUEUR', 'XAUGBP', 'XAUJPY', 'XAUUSD'],
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
        $XAUUSD = $quotes['XAUUSD'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // XAUI = USDLFX * XAUUSD
        foreach ($XAUUSD as $i => $bar) {
            $xauusd = $XAUUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx * $xauusd, $digits);
            $iOpen  = (int) round($open/$point);

            $xauusd = $XAUUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx * $xauusd, $digits);
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
