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
 * CADLFX synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic LiteForex Canadian Dollar index.
 *
 * <pre>
 * Formulas:
 * ---------
 * CADLFX = USDLFX / USDCAD
 * CADLFX = pow(CADCHF * CADJPY / (AUDCAD * EURCAD * GBPCAD * USDCAD), 1/7)
 * </pre>
 *
 * @phpstan-import-type  PRICE_BAR from \rosasurfer\rt\Rosatrader
 */
class CADLFX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'fast'    => ['USDCAD', 'USDLFX'],
        'crosses' => ['AUDCAD', 'CADCHF', 'CADJPY', 'EURCAD', 'GBPCAD', 'USDCAD'],
    ];


    /**
     * {@inheritdoc}
     *
     * @param  int $period
     * @param  int $time
     *
     * @return array[] - PRICE_BAR array with history data
     * @phpstan-return PRICE_BAR[]
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
        $USDCAD = $quotes['USDCAD'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // CADLFX = USDLFX / USDCAD
        foreach ($USDCAD as $i => $bar) {
            $usdcad = $USDCAD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx / $usdcad, $digits);
            $iOpen  = (int) round($open/$point);

            $usdcad = $USDCAD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx / $usdcad, $digits);
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
