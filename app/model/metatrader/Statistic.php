<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\PersistableObject;

use function rosasurfer\xtrade\stats_sharpe_ratio;
use function rosasurfer\xtrade\stats_sortino_ratio;


/**
 * Represents the statistics record of a {@link Test}.
 *
 * @method int   getId()           Return the id (primary key) of the statistics record.
 * @method int   getTrades()       Return the number of trades of the statistics record.
 * @method float getTradesPerDay() Return the trades per day number of the statistics record.
 * @method int   getMinDuration()  Return the minimum trade duration of the statistics record.
 * @method int   getAvgDuration()  Return the average trade duration of the statistics record.
 * @method int   getMaxDuration()  Return the maximum trade duration of the statistics record.
 * @method float getMinPips()      Return the minimum amount of won/lost pips of the statistics record.
 * @method float getAvgPips()      Return the average amount of won/lost pips of the statistics record.
 * @method float getMaxPips()      Return the maximum amount of won/loast of the statistics record.
 * @method float getPips()         Return the sum of all won/lost pips of the statistics record.
 * @method float getGrossProfit()  Return the total gross gross profit of the statistics record.
 * @method float getCommission()   Return the total commission amount of the statistics record.
 * @method float getSwap()         Return the total swap amount of the statistics record.
 * @method float getSharpeRatio()  Return the Sharpe ratio of the statistics record.
 * @method float getSortinoRatio() Return the Sortino ratio of the statistics record.
 * @method Test  getTest()         Return the test the statistics record belongs to.
 */
class Statistic extends PersistableObject {


    /** @var int */
    protected $id;

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
    protected $grossProfit;

    /** @var float */
    protected $commission;

    /** @var float */
    protected $swap;

    /** @var float */
    protected $sharpeRatio;

    /** @var float */
    protected $sortinoRatio;

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
        $trades       = $test->getTrades();
        $numTrades    = sizeOf($trades);
        $testDuration = (strToTime($test->getEndTime().' GMT') - strToTime($test->getStartTime().' GMT'))/DAYS;
        $tradesPerDay = $testDuration ? round($numTrades/($testDuration * 5/7), 1) : 0;

        $minDuration = PHP_INT_MAX;
        $maxDuration = 0;
        $sumDuration = 0;

        $minPips = PHP_INT_MAX;
        $maxPips = 0;
        $sumPips = 0;

        $profit  = $commission = $swap = 0;
        $returns = [];

        /** @var Order $trade */
        foreach ($trades as $trade) {
            $tsOpen  = strToTime($trade->getOpenTime() .' GMT');
            $tsClose = strToTime($trade->getCloseTime().' GMT');

            $duration    = $tsClose - $tsOpen;
            $minDuration = min($minDuration, $duration);
            $maxDuration = max($maxDuration, $duration);
            $sumDuration += ($tsClose - $tsOpen);

            $type       = strToLower($trade->getType());
            $openPrice  = $trade->getOpenPrice();
            $closePrice = $trade->getClosePrice();

            $pips    = round(($type==='buy' ? $closePrice-$openPrice : $openPrice-$closePrice)/0.0001, 1);
            $minPips = min($minPips, $pips);
            $maxPips = max($maxPips, $pips);
            $sumPips += $pips;

            $profit     += $trade->getProfit();
            $commission += $trade->getCommission();
            $swap       += $trade->getSwap();

            $returns[] = $pips;
        }

        $stats->trades       = $numTrades;
        $stats->tradesPerDay = $tradesPerDay;

        $stats->minDuration  = ($minDuration==PHP_INT_MAX) ? 0 : $minDuration;
        $stats->avgDuration  = $numTrades ? round($sumDuration/$numTrades) : 0;
        $stats->maxDuration  = $maxDuration;

        $stats->minPips      = round($minPips==PHP_INT_MAX ? 0 : $minPips, 1);
        $stats->avgPips      = round($numTrades ? $sumPips/$numTrades : 0, 1);
        $stats->maxPips      = round($maxPips, 1);
        $stats->pips         = round($sumPips, 1);

        $stats->grossProfit  = round($profit, 2);
        $stats->commission   = round($commission, 2);
        $stats->swap         = round($swap, 2);

        $stats->sharpeRatio  = stats_sharpe_ratio($returns);
        $stats->sortinoRatio = stats_sortino_ratio($returns);

        return $stats;
    }
}
