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
 * EURX synthesizer
 *
 * A {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} for calculating the official ICE Euro index.
 *
 * <pre>
 * Formula:
 * --------
 * EURX = 34.38805726 * EURUSD * pow(USDCHF, 0.1113) * pow(USDJPY, 0.1891) * pow(USDSEK, 0.0785) / pow(GBPUSD, 0.3056)
 * EURX = 34.38805726 * pow(EURCHF, 0.1113) * pow(EURGBP, 0.3056) * pow(EURJPY, 0.1891) * pow(EURSEK, 0.0785) * pow(EURUSD, 0.3155)
 * </pre>
 */
class EURX extends AbstractSynthesizer {


    /** @var string[][] */
    protected $components = [
        'majors'  => ['EURUSD', 'GBPUSD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'crosses' => ['EURCHF', 'EURGBP', 'EURJPY', 'EURSEK', 'EURUSD'],
    ];


    /**
     * {@inheritdoc}
     *
     * @param  int $period
     * @param  int $time
     *
     * @return array<PRICE_BAR> - PRICE_BAR array
     *
     * @see    \rosasurfer\rt\PRICE_BAR
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
        $EURUSD = $quotes['EURUSD'];
        $GBPUSD = $quotes['GBPUSD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];
        $USDSEK = $quotes['USDSEK'];
        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPointValue();
        $bars   = [];

        // EURX = 34.38805726 * EURUSD * pow(USDCHF, 0.1113) * pow(USDJPY, 0.1891) * pow(USDSEK, 0.0785) / pow(GBPUSD, 0.3056)
        foreach ($EURUSD as $i => $bar) {
            $eurusd = $EURUSD[$i]['open'];
            $gbpusd = $GBPUSD[$i]['open'];
            $usdchf = $USDCHF[$i]['open'];
            $usdjpy = $USDJPY[$i]['open'];
            $usdsek = $USDSEK[$i]['open'];
            $open   = 34.38805726 * $eurusd * pow($usdchf, 0.1113) * pow($usdjpy, 0.1891) * pow($usdsek, 0.0785) / pow($gbpusd, 0.3056);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $eurusd = $EURUSD[$i]['close'];
            $gbpusd = $GBPUSD[$i]['close'];
            $usdchf = $USDCHF[$i]['close'];
            $usdjpy = $USDJPY[$i]['close'];
            $usdsek = $USDSEK[$i]['close'];
            $close  = 34.38805726 * $eurusd * pow($usdchf, 0.1113) * pow($usdjpy, 0.1891) * pow($usdsek, 0.0785) / pow($gbpusd, 0.3056);
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
