<?php
namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\synthetic\AbstractSynthesizer;

use function rosasurfer\ministruts\first;
use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * SEKFXI synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic Swedish Krona currency index. Due to the Krona's low
 * value the index is scaled-up by a factor of 10. This adjustment only effects the nominal scala, not the shape of the SEK index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * SEKFXI = 10 * USDLFX / USDSEK
 * SEKFXI = 10 * pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7) / USDSEK
 * SEKFXI = 10 * pow(SEKJPY / (AUDSEK * CADSEK * CHFSEK * EURSEK * GBPSEK * USDSEK), 1/7)
 * </pre>
 */
class SEKFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDLFX', 'USDSEK'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'crosses' => ['AUDSEK', 'CADSEK', 'CHFSEK', 'EURSEK', 'GBPSEK', 'SEKJPY', 'USDSEK'],
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
        $USDSEK = $quotes['USDSEK'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // SEKFXI = 10 * USDLFX / USDSEK
        foreach ($USDSEK as $i => $bar) {
            $usdsek = $USDSEK[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = 10 * $usdlfx / $usdsek;
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdsek = $USDSEK[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = 10 * $usdlfx / $usdsek;
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
