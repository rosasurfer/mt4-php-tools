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
 * ZARFXI synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic South African Rand currency index. Due to the Rand's
 * low value the index is scaled-up by a factor of 10. This adjustment only effects the nominal scala, not the shape of the ZAR index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * ZARFXI = 10 * USDLFX / USDZAR
 * ZARFXI = 10 * pow(USDCAD * USDCHF *USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7) / USDZAR
 * ZARFXI = 10 * pow(ZARJPY / (AUDZAR * CADZAR * CHFZAR * EURZAR * GBPZAR * USDZAR), 1/7)
 * </pre>
 *
 * @phpstan-import-type  PRICE_BAR from \rosasurfer\rt\Rosatrader
 */
class ZARFXI extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDLFX', 'USDZAR'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDZAR'],
        'crosses' => ['AUDZAR', 'CADZAR', 'CHFZAR', 'EURZAR', 'GBPZAR', 'USDZAR', 'ZARJPY'],
    ];


    /**
     * {@inheritdoc}
     *
     * @param  int $period
     * @param  int $time
     *
     * @return PRICE_BAR[] - array with history data
     *
     * @see  \rosasurfer\rt\PRICE_BAR
     */
    public function calculateHistory(int $period, int $time): array {
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
        $USDZAR = $quotes['USDZAR'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // ZARFXI = 10 * USDLFX / USDZAR
        foreach ($USDZAR as $i => $bar) {
            $usdzar = $USDZAR[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = 10 * $usdlfx / $usdzar;
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdzar = $USDZAR[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = 10 * $usdlfx / $usdzar;
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
