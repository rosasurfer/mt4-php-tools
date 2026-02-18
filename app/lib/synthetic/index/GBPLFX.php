<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\synthetic\index;

use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;
use rosasurfer\ministruts\core\proxy\Output;

use rosasurfer\rt\lib\synthetic\Synthesizer;

use function rosasurfer\ministruts\first;
use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;

/**
 * GBPLFX synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic LiteForex Great Britain Pound index.
 *
 * <pre>
 * Formulas:
 * ---------
 * GBPLFX = USDLFX * GBPUSD
 * GBPLFX = pow(GBPAUD * GBPCAD * GBPCHF * GBPJPY * GBPUSD / EURGBP, 1/7)
 * </pre>
 *
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
class GBPLFX extends Synthesizer
{
    /** @var string[][] */
    protected array $components = [
        'fast'    => ['GBPUSD', 'USDLFX'],
        'crosses' => ['EURGBP', 'GBPAUD', 'GBPCAD', 'GBPCHF', 'GBPJPY', 'GBPUSD'],
    ];


    /**
     * {@inheritdoc}
     *
     * @phpstan-return RT_PRICE_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public function calculateHistory(int $period, int $time): array {
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');

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
        $GBPUSD = $quotes['GBPUSD'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // GBPLFX = USDLFX * GBPUSD
        foreach ($GBPUSD as $i => $bar) {
            $gbpusd = $GBPUSD[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = round($usdlfx * $gbpusd, $digits);
            $iOpen  = (int) round($open/$point);

            $gbpusd = $GBPUSD[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = round($usdlfx * $gbpusd, $digits);
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
