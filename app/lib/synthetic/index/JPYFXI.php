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
 * JPYFXI synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the synthetic Japanese Yen currency index. Due to the Yen's low
 * value the index is scaled-up by a factor of 100. This adjustment only effects the nominal scala, not the shape of the JPY index chart.
 *
 * <pre>
 * Formulas:
 * ---------
 * JPYFXI = 100 / pow(USDLFX / USDJPY, 7/6)
 * JPYFXI = 100 * pow(USDCAD * USDCHF / (AUDUSD * EURUSD * GBPUSD), 1/6) * USDJPY
 * JPYFXI = 100 * pow(1 / (AUDJPY * CADJPY * CHFJPY * EURJPY * GBPJPY * USDJPY), 1/6)
 * </pre>
 *
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
class JPYFXI extends Synthesizer {


    /** @var string[][] */
    protected array $components = [
        'fast'    => ['USDJPY', 'USDLFX'],
        'majors'  => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'crosses' => ['AUDJPY', 'CADJPY', 'CHFJPY', 'EURJPY', 'GBPJPY', 'USDJPY'],
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
        $USDJPY = $quotes['USDJPY'];
        $USDLFX = $quotes['USDLFX'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // JPYFXI = 100 / pow(USDLFX / USDJPY, 7/6)
        foreach ($USDJPY as $i => $bar) {
            $usdjpy = $USDJPY[$i]['open'];
            $usdlfx = $USDLFX[$i]['open'];
            $open   = 100 / pow($usdlfx / $usdjpy, 7/6);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $usdjpy = $USDJPY[$i]['close'];
            $usdlfx = $USDLFX[$i]['close'];
            $close  = 100 / pow($usdlfx / $usdjpy, 7/6);
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
