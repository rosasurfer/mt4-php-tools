<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\PersistableObject;


/**
 * Represents statistics of a {@link Test}.
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
    protected $profit;

    /** @var float */
    protected $commission;

    /** @var float */
    protected $swap;

    /** @var int */
    protected $test_id;

    /** @var Test */
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

        $profit = $commission = $swap = 0;

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

        $stats->profit       = round($profit, 2);
        $stats->commission   = round($commission, 2);
        $stats->swap         = round($swap, 2);

        return $stats;
    }


    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getTrades() {
        return $this->trades;
    }


    /**
     * @return float
     */
    public function getTradesPerDay() {
        return $this->tradesPerDay;
    }


    /**
     * @return int
     */
    public function getMinDuration() {
        return $this->minDuration;
    }


    /**
     * @return int
     */
    public function getAvgDuration() {
        return $this->avgDuration;
    }


    /**
     * @return int
     */
    public function getMaxDuration() {
        return $this->maxDuration;
    }


    /**
     * @return float
     */
    public function getMinPips() {
        return $this->minPips;
    }


    /**
     * @return float
     */
    public function getAvgPips() {
        return $this->avgPips;
    }


    /**
     * @return float
     */
    public function getMaxPips() {
        return $this->maxPips;
    }


    /**
     * @return float
     */
    public function getPips() {
        return $this->pips;
    }


    /**
     * @return float
     */
    public function getProfit() {
        return $this->profit;
    }


    /**
     * @return float
     */
    public function getCommission() {
        return $this->commission;
    }


    /**
     * @return float
     */
    public function getSwap() {
        return $this->swap;
    }


    /**
     * @return int
     */
    public function getTest_id() {
        if ($this->test_id === null) {
            if (is_object($this->test))
                $this->test_id = $this->test->getId();
        }
        return $this->test_id;
    }


    /**
     * @return Test
     */
    public function getTest() {
        if ($this->test === null) {
            if ($this->test_id)
                $this->test = Test::dao()->findById($this->test_id);
        }
        return $this->test;
    }


    /**
     * Insert pre-processing hook. Assign the {@link Test} id as this is not yet automated by the ORM.
     *
     * {@inheritDoc}
     */
    protected function beforeInsert() {
        if (!$this->test_id)
            $this->test_id = $this->test->getId();
        return true;
    }
}
