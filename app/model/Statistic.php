<?php
namespace rosasurfer\rt\model;

use function rosasurfer\rt\stats_calmar_ratio;
use function rosasurfer\rt\stats_sharpe_ratio;
use function rosasurfer\rt\stats_sortino_ratio;


/**
 * Represents the statistics record of a {@link Test}.
 *
 * @method int   getTrades()          Return the number of trades of the test.
 * @method float getTradesPerDay()    Return the number of trades per day of the test.
 * @method int   getMinDuration()     Return the minimum trade duration in seconds.
 * @method int   getAvgDuration()     Return the average trade duration in seconds.
 * @method int   getMaxDuration()     Return the maximum trade duration in seconds.
 * @method float getMinPips()         Return the minimum amount of won/lost pips of the test.
 * @method float getAvgPips()         Return the average amount of won/lost pips of the test.
 * @method float getMaxPips()         Return the maximum amount of won/lost pips of the test.
 * @method float getPips()            Return the sum of won/lost pips of the test.
 * @method float getSharpeRatio()     Return the Sharpe ratio of the test.
 * @method float getSortinoRatio()    Return the Sortino ratio of the test.
 * @method float getCalmarRatio()     Return the Calmar ratio of the test.
 * @method int   getMaxRecoveryTime() Return the maximum drawdown recovery time in seconds.
 * @method float getGrossProfit()     Return the total gross profit of the test.
 * @method float getCommission()      Return the total commission amount of the test.
 * @method float getSwap()            Return the total swap amount of the test.
 * @method Test  getTest()            Return the test the statistics record belongs to.
 */
class Statistic extends RosatraderModel {


    /** @var int */
    protected $trades;

    /** @var float */
    protected $tradesPerDay;

    /** @var int */
    protected $minDuration;

    /** @var int */
    protected $avgDuration;

    /** @var int */
    protected $maxDuration;

    /** @var float */
    protected $minPips;

    /** @var float */
    protected $avgPips;

    /** @var float */
    protected $maxPips;

    /** @var float */
    protected $pips;

    /** @var float */
    protected $sharpeRatio;

    /** @var float */
    protected $sortinoRatio;

    /** @var float */
    protected $calmarRatio;

    /** @var int */
    protected $maxRecoveryTime;

    /** @var float */
    protected $grossProfit;

    /** @var float */
    protected $commission;

    /** @var float */
    protected $swap;

    /** @var Test [transient] */
    protected $test;


    /**
     * Create a new {@link Test} statistics instance.
     *
     * @param  Test $test - the test the statistics belong to
     *
     * @return self
     */
    public static function create(Test $test) {
        $stats = new self();
        $stats->test = $test;

        // calculate statistics
        $trades       = $test->getTrades();                                 // TODO: order by closeTime
        $numTrades    = sizeof($trades);
        $testDuration = (strtotime($test->getEndTime().' GMT') - strtotime($test->getStartTime().' GMT'))/DAYS;
        $tradesPerDay = $testDuration ? round($numTrades/($testDuration * 5/7), 1) : 0;

        $minDuration = PHP_INT_MAX;
        $maxDuration = 0;
        $sumDuration = 0;

        $minPips = PHP_INT_MAX;
        $maxPips = 0;
        $sumPips = $lastHigh = 0;
        $ddStart = $maxRecoveryTime = $tsClose = 0;

        $profit  = $commission = $swap = 0;
        $returns = [];

        /** @var Order $trade */
        foreach ($trades as $trade) {
            $tsOpen  = strtotime($trade->getOpenTime() .' GMT');            // $ts => timestamp
            $tsClose = strtotime($trade->getCloseTime().' GMT');

            $duration    = $tsClose - $tsOpen;
            $minDuration = min($minDuration, $duration);
            $maxDuration = max($maxDuration, $duration);
            $sumDuration += ($tsClose - $tsOpen);

            $type       = strtolower($trade->getType());
            $openPrice  = $trade->getOpenPrice();
            $closePrice = $trade->getClosePrice();

            $pips     = round(($type==='buy' ? $closePrice-$openPrice : $openPrice-$closePrice)/0.0001, 1);
            $minPips  = min($minPips, $pips);
            $maxPips  = max($maxPips, $pips);
            $sumPips += $pips;

            $profit     += $trade->getProfit();
            $commission += $trade->getCommission();
            $swap       += $trade->getSwap();

            $returns[] = $pips;

            if ($pips < 0) {                                                            // a loss
                if (!$ddStart) {
                    $ddStart  = $tsClose;                                               // start of drawdown
                    $lastHigh = $sumPips - $pips;
                }
            }
            else if ($pips > 0) {                                                       // a profit
                if ($ddStart) {                                                         // during drawdown
                    if ($sumPips > $lastHigh) {                                         // causing a new high
                        $maxRecoveryTime = max($tsClose-$ddStart, $maxRecoveryTime);
                        $ddStart = 0;                                                   // the drawdown is recovered
                    }
                }
            }
        }
        if ($ddStart) $maxRecoveryTime = max($tsClose-$ddStart, $maxRecoveryTime);


        $stats->trades          = $numTrades;
        $stats->tradesPerDay    = $tradesPerDay;

        $stats->minDuration     = ($minDuration==PHP_INT_MAX) ? 0 : $minDuration;
        $stats->avgDuration     = $numTrades ? round($sumDuration/$numTrades) : 0;
        $stats->maxDuration     = $maxDuration;

        $stats->minPips         = round($minPips==PHP_INT_MAX ? 0 : $minPips, 1);
        $stats->avgPips         = round($numTrades ? $sumPips/$numTrades : 0, 1);
        $stats->maxPips         = round($maxPips, 1);
        $stats->pips            = round($sumPips, 1);

        $stats->sharpeRatio     = stats_sharpe_ratio($returns);
        $stats->sortinoRatio    = stats_sortino_ratio($returns);
        $stats->calmarRatio     = stats_calmar_ratio($test->getStartTime(), $test->getEndTime(), $returns);
        $stats->maxRecoveryTime = $maxRecoveryTime;

        $stats->grossProfit     = round($profit, 2);
        $stats->commission      = round($commission, 2);
        $stats->swap            = round($swap, 2);

        return $stats;
    }
}
