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
 * USDLFX synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic LiteForex US Dollar index.
 *
 * <pre>
 * Formula:
 * --------
 * USDLFX = pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7)
 * </pre>
 *
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
class USDLFX extends Synthesizer
{
    /** @var string[][] */
    protected array $components = [
        'majors' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
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
        $AUDUSD = $quotes['AUDUSD'];
        $EURUSD = $quotes['EURUSD'];
        $GBPUSD = $quotes['GBPUSD'];
        $USDCAD = $quotes['USDCAD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // USDLFX = pow(USDCAD * USDCHF * USDJPY / (AUDUSD * EURUSD * GBPUSD), 1/7)
        foreach ($AUDUSD as $i => $bar) {
            $audusd = $AUDUSD[$i]['open'];
            $eurusd = $EURUSD[$i]['open'];
            $gbpusd = $GBPUSD[$i]['open'];
            $usdcad = $USDCAD[$i]['open'];
            $usdchf = $USDCHF[$i]['open'];
            $usdjpy = $USDJPY[$i]['open'];
            $open   = pow($usdcad * $usdchf * $usdjpy / ($audusd * $eurusd * $gbpusd), 1/7);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $audusd = $AUDUSD[$i]['close'];
            $eurusd = $EURUSD[$i]['close'];
            $gbpusd = $GBPUSD[$i]['close'];
            $usdcad = $USDCAD[$i]['close'];
            $usdchf = $USDCHF[$i]['close'];
            $usdjpy = $USDJPY[$i]['close'];
            $close  = pow($usdcad * $usdchf * $usdjpy / ($audusd * $eurusd * $gbpusd), 1/7);
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
