<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\util\PHP;
use rosasurfer\ministruts\util\Windows;

use rosasurfer\rt\lib\metatrader\MT4;
use rosasurfer\rt\lib\rosatrader\Rost;

use function rosasurfer\ministruts\ini_get_int;
use function rosasurfer\ministruts\normalizeEOL;
use function rosasurfer\ministruts\strContains;
use function rosasurfer\ministruts\strEndsWith;
use function rosasurfer\ministruts\strIsDigits;
use function rosasurfer\ministruts\strIsDoubleQuoted;
use function rosasurfer\ministruts\strIsNumeric;
use function rosasurfer\ministruts\strLeftTo;
use function rosasurfer\ministruts\strRight;
use function rosasurfer\ministruts\strStartsWith;

use const rosasurfer\ministruts\NL;

use const rosasurfer\rt\BARMODEL_BAROPEN;
use const rosasurfer\rt\OP_SELL;
use const rosasurfer\rt\PERIOD_M1;


/**
 * Represents a test executed in the MetaTrader Strategy Tester.
 *
 * @method        string                                   getStrategy()           Return the name of the tested strategy.
 * @method        \rosasurfer\rt\model\StrategyParameter[] getStrategyParameters() Return the strategy parameters of the test.
 * @method        int                                      getReportingId()        Return the reporting id of the test (for composition of the reporting symbol).
 * @method        string                                   getReportingSymbol()    Return the reporting symbol of the test (for charted reports).
 * @method        string                                   getSymbol()             Return the symbol of the tested instrument.
 * @method        int                                      getTimeframe()          Return the tested timeframe.
 * @method        string                                   getStartTime()          Return the time of the first tested tick (FXT).
 * @method        string                                   getEndTime()            Return the time of the last tested tick (FXT).
 * @method        string                                   getBarModel()           Return the bar model used for the test.
 * @method        float                                    getSpread()             Return the spread used for the test.
 * @method        int                                      getBars()               Return the number of tested bars.
 * @method        int                                      getTicks()              Return the number of tested ticks.
 * @method        string                                   getTradeDirections()    Return the enabled trade directions of the test.
 * @method        \rosasurfer\rt\model\Order[]             getTrades()             Return the trade history of the test.
 * @method static \rosasurfer\rt\model\TestDAO             dao()                   Return the DAO for the class.
 *
 * @phpstan-import-type LOG_ORDER from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type LOG_TEST  from \rosasurfer\rt\phpstan\CustomTypes
 */
class Test extends RosatraderModel {


    /** @var string - strategy name */
    protected $strategy;

    /** @var StrategyParameter[] [transient] - strategy input parameters */
    protected $strategyParameters;

    /** @var int - reporting id (for composition of reportingSymbol) */
    protected $reportingId;

    /** @var string - test symbol for charted reports */
    protected $reportingSymbol;

    /** @var string - tested symbol */
    protected $symbol;

    /** @var int - tested timeframe */
    protected $timeframe;

    /** @var string - time of the first tick of testing (FXT) */
    protected $startTime;

    /** @var string - time of the last tick of testing (FXT) */
    protected $endTime;

    /** @var string - used bar model: EveryTick|ControlPoints|BarOpen */
    protected $barModel;

    /** @var float - spread in pips */
    protected $spread;

    /** @var int - number of tested bars */
    protected $bars;

    /** @var int - number of tested ticks */
    protected $ticks;

    /** @var string - enabled trade directions: Long|Short|Both */
    protected $tradeDirections;

    /** @var Order[] [transient] - trade history of the test */
    protected $trades;

    /** @var Statistic [transient] - test statistics */
    protected $stats;


    /**
     * Create a new Test instance from the provided data files.
     *
     * @param  string $configFile  - name of the test configuration file
     * @param  string $resultsFile - name of the test results file
     *
     * @return self
     */
    public static function create(string $configFile, string $resultsFile) {
        $test = new self();
        $test->created = date('Y-m-d H:i:s');

        // parse the test results file
        PHP::ini_set('auto_detect_line_endings', 1);
        $hFile = fopen($resultsFile, 'rb');
        $i = 0;

        while (($line=fgets($hFile)) !== false) {
            $i++;
            if (!strlen($line=trim($line))) continue;

            if (!$test->startTime) {
                // first line: test properties
                $properties = self::parseTestProperties($line);

                $time = $properties['time'];                  // GMT timestamp
                if ($time <= 0)                               throw new InvalidValueException('Invalid property "time": '.$time.' (not positive)');
                $test->created = gmdate('Y-m-d H:i:s', $time);

                $strategy = $properties['strategy'];
                if (strlen($strategy) > Windows::MAX_PATH)    throw new InvalidValueException('Invalid property "strategy": "'.$strategy.'" (length violation)');
                $test->strategy = $strategy;

                $reportingId = $properties['reportingId'];
                if ($reportingId <= 0)                        throw new InvalidValueException('Invalid property "reportingId": '.$reportingId.' (not positive)');
                $test->reportingId = $reportingId;

                $symbol = $properties['reportingSymbol'];
                if (strlen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidValueException('Invalid property "reportingSymbol": "'.$symbol.'" (length violation)');
                $test->reportingSymbol = $symbol;

                $symbol = $properties['symbol'];
                if (strlen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidValueException('Invalid property "symbol": "'.$symbol.'" (length violation)');
                $test->symbol = $symbol;

                $timeframe = $properties['timeframe'];
                if (!MT4::isStdTimeframe($timeframe))         throw new InvalidValueException('Invalid property "timeframe": '.$timeframe.' (not a timeframe)');
                $test->timeframe = $timeframe;

                $startTime = $properties['startTime'];        // FXT timestamp
                if ($startTime <= 0)                          throw new InvalidValueException('Invalid property "startTime": '.$startTime.' (not positive)');
                $test->startTime = gmdate('Y-m-d H:i:s', $startTime);

                $endTime = $properties['endTime'];            // FXT timestamp
                if ($endTime <= 0)                            throw new InvalidValueException('Invalid property "endTime": '.$endTime.' (not positive)');
                if ($startTime > $endTime)                    throw new InvalidValueException('Invalid properties "startTime|endTime": '.$startTime.'|'.$endTime.' (mis-match)');
                $test->endTime = gmdate('Y-m-d H:i:s', $endTime);

                $barModel = $properties['barModel'];
                if (!MT4::isBarModel($barModel))              throw new InvalidValueException('Invalid property "barModel": '.$barModel.' (not a bar model)');
                $test->barModel = MT4::barModelDescription($barModel);

                $spread = $properties['spread'];
                if ($spread < 0)                              throw new InvalidValueException('Invalid property "spread": '.$spread.' (not non-negative)');
                if ($spread != round($spread, 1))             throw new InvalidValueException('Invalid property "spread": '.$spread.' (illegal)');
                $test->spread = $spread;

                $bars = $properties['bars'];
                if ($bars <= 0)                               throw new InvalidValueException('Invalid property "bars": '.$bars.' (not positive)');
                $test->bars = $bars;

                $ticks = $properties['ticks'];
                if ($ticks <= 0)                              throw new InvalidValueException('Invalid property "ticks": '.$ticks.' (not positive)');
                $test->ticks = $ticks;

                if ($ticks == $bars+1) {
                    $test->barModel = MT4::barModelDescription(BARMODEL_BAROPEN);
                }
                continue;
            }

            // all further lines: trades (order properties)
            if (!strStartsWith($line, 'order.')) throw new InvalidValueException('Unsupported file format in line '.$i.' of "'.$resultsFile.'"');
            $order = Order::create($test, self::parseOrderProperties($line));

            if ($order->isClosedPosition()) {                           // open positions are skipped
                $test->trades[] = $order;
            }
        }
        fclose($hFile);

        // parse the test config file
        $content = normalizeEOL(file_get_contents($configFile));

        static $pcreLimit = null;
        if (!$pcreLimit) {
            $pcreLimit = ini_get_int('pcre.backtrack_limit');
        }
        if (strlen($content) > $pcreLimit) {                            // increase RegExp limit if needed
            PHP::ini_set('pcre.backtrack_limit', $pcreLimit = strlen($content));
        }

        // tradeDirections
        $pattern = '|^\s*<common\s*>\s*\n(?:.*\n)*(\s*positions\s*=\s*(.+)\s*\n)(?:.*\n)*\s*</common>|imU';
        $matches = null;
        if (!preg_match($pattern, $content, $matches))                throw new InvalidValueException('Unsupported file format in test config file "'.$configFile.'" ("/common positions" not found)');
        if (($direction = MT4::strToTradeDirection($matches[2])) < 0) throw new InvalidValueException('Illegal test property "tradeDirections": "'.$matches[2].'"');
        $test->tradeDirections = MT4::tradeDirectionDescription($direction);

        // input parameters
        $pattern = '|^\s*<inputs\s*>\s*\n(.*)^\s*</inputs>\s*$|ismU';
        if (!preg_match($pattern, $content, $matches)) throw new InvalidValueException('Unsupported file format in test config file "'.$configFile.'" ("/inputs" not found)');
        $params = explode(NL, trim($matches[1]));

        foreach ($params as $i => $line) {
            if (strlen($line = trim($line))) {
                $values = explode('=', $line, 2);
                if (sizeof($values) < 2) throw new InvalidValueException('Illegal input parameter "'.$params[$i].'" in test config file "'.$configFile.'"');
                if (strContains($values[0], ',')) {
                    continue;
                }
                $test->strategyParameters[] = StrategyParameter::create($test, $values[0], $values[1]);
            }
        }
        return $test;
    }


    /**
     * Return the statistics of the Test.
     *
     * @return Statistic
     */
    public function getStats() {
        $stats = $this->get('stats');
        if (!$stats)
            $this->stats = Statistic::create($this);
        return $this->stats;
    }


    /**
     * Return the number of trades of the Test.
     *
     * @return int
     */
    public function countTrades() {
        return sizeof($this->getTrades());
    }


    /**
     * Parse a string with test properties.
     *
     * @param  string $values - test property values from a log file
     *
     * @return         scalar[] - LOG_TEST array with test properties
     * @phpstan-return LOG_TEST
     *
     * @see \rosasurfer\rt\phpstan\LOG_TEST
     */
    protected static function parseTestProperties(string $values): array {
        $valuesOrig = $values;
        $values = trim($values);
        $properties = [];

        $oldTimezone = date_default_timezone_get();
        try {
            static $pcreLimit = null;
            if (!$pcreLimit) {
                $pcreLimit = ini_get_int('pcre.backtrack_limit');
            }
            if (strlen($values) > $pcreLimit) {     // increase RegExp limit if needed
                PHP::ini_set('pcre.backtrack_limit', $pcreLimit = strlen($values));
            }

            // test={id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", barModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451}
            if (!strStartsWith($values, 'test=')) throw new InvalidValueException('Unsupported test properties format: "'.$valuesOrig.'"');
            $values = trim(strRight($values, -5));

            // {id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", barModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451}
            if (!strStartsWith($values, '{') || !strEndsWith($values, '}')) throw new InvalidValueException('Unsupported test properties format: "'.$valuesOrig.'"');
            $values = ', '.trim(substr($values, 1, strlen($values)-2)).', ';
            // ', id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", barModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451, '

            // id
            $pattern = '/, *id *= *([0-9]+) *,/i';
            $matches = null;
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("id" invalid or not found): "'.$valuesOrig.'"');
            $properties['id'] = (int)$matches[1][0];
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "id" occurrences): "'.$valuesOrig.'"');

            // time (local)
            $pattern = '/, *time *= *"([^"]+)" *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("time" invalid or not found): "'.$valuesOrig.'"');

            date_default_timezone_set(ini_get('date.timezone'));
            if (!$time = strtotime($matches[1][0]))                            throw new InvalidValueException('Illegal test property "time": "'.$matches[1][0].'"');
            $properties['time'] = $time;
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "time" occurrences): "'.$valuesOrig.'"');

            // strategy
            $pattern = '/, *strategy *= *"([^"]+)" *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("strategy" invalid or not found): "'.$valuesOrig.'"');
            $properties['strategy'] = trim($matches[1][0]);
            if (!strlen($properties['strategy']))                              throw new InvalidValueException('Illegal test properties ("strategy" invalid): "'.$valuesOrig.'"');
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "strategy" occurrences): "'.$valuesOrig.'"');

            // reportingId
            $pattern = '/, *reportingId *= *([0-9]+) *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("reportingId" invalid or not found): "'.$valuesOrig.'"');
            $properties['reportingId'] = (int)$matches[1][0];
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "reportingId" occurrences): "'.$valuesOrig.'"');

            // reportingSymbol
            $pattern = '/, *reportingSymbol *= *"([^"]+)" *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("reportingSymbol" invalid or not found): "'.$valuesOrig.'"');
            $properties['reportingSymbol'] = trim($matches[1][0]);
            if (!strlen($properties['reportingSymbol']))                       throw new InvalidValueException('Illegal test properties ("reportingSymbol" invalid): "'.$valuesOrig.'"');
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "reportingSymbol" occurrences): "'.$valuesOrig.'"');

            // symbol
            $pattern = '/, *symbol *= *"([^"]+)" *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("symbol" invalid or not found): "'.$valuesOrig.'"');
            $properties['symbol'] = trim($matches[1][0]);
            if (!strlen($properties['symbol']))                                throw new InvalidValueException('Illegal test properties ("symbol" invalid): "'.$valuesOrig.'"');
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "symbol" occurrences): "'.$valuesOrig.'"');

            // timeframe
            $pattern = '/, *timeframe *= *(PERIOD_[^ ]+) *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("timeframe" invalid or not found): "'.$valuesOrig.'"');
            if (!$id = MT4::strToTimeframe($matches[1][0]))                    throw new InvalidValueException('Illegal test property "timeframe": "'.$matches[1][0].'"');
            $properties['timeframe'] = $id;
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "timeframe" occurrences): "'.$valuesOrig.'"');

            // startTime (FXT)
            $pattern = '/, *startTime *= *"([^"]+)" *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("startTime" invalid or not found): "'.$valuesOrig.'"');
            date_default_timezone_set('GMT');
            if (!$time = strtotime($matches[1][0]))                            throw new InvalidValueException('Illegal test property "startTime": "'.$matches[1][0].'"');
            $properties['startTime'] = $time;
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "startTime" occurrences): "'.$valuesOrig.'"');

            // endTime (FXT)
            $pattern = '/, *endTime *= *"([^"]+)" *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("endTime" invalid or not found): "'.$valuesOrig.'"');
            date_default_timezone_set('GMT');
            if (!$time = strtotime($matches[1][0]))                            throw new InvalidValueException('Illegal test property "endTime": "'.$matches[1][0].'"');
            $properties['endTime'] = $time;
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "endTime" occurrences): "'.$valuesOrig.'"');

            // barModel
            $pattern = '/, *barModel *= *([0-9]+) *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("barModel" invalid or not found): "'.$valuesOrig.'"');
            if (($id = MT4::strToBarModel($matches[1][0])) < 0)                throw new InvalidValueException('Illegal test property "barModel": "'.$matches[1][0].'"');
            $properties['barModel'] = $id;
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "barModel" occurrences): "'.$valuesOrig.'"');

            // spread
            $pattern = '/, *spread *= *([^ ]+) *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("spread" invalid or not found): "'.$valuesOrig.'"');
            if (!strIsNumeric($spread = $matches[1][0]))                       throw new InvalidValueException('Illegal test property "spread": "'.$matches[1][0].'"');
            $properties['spread'] = (float)$spread;
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "spread" occurrences): "'.$valuesOrig.'"');

            // bars
            $pattern = '/, *bars *= *([0-9]+) *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("bars" invalid or not found): "'.$valuesOrig.'"');
            $properties['bars'] = (int)$matches[1][0];
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "bars" occurrences): "'.$valuesOrig.'"');

            // ticks
            $pattern = '/, *ticks *= *([0-9]+) *,/i';
            if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal test properties ("ticks" invalid or not found): "'.$valuesOrig.'"');
            $properties['ticks'] = (int)$matches[1][0];
            if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal test properties (multiple "ticks" occurrences): "'.$valuesOrig.'"');
        }
        finally {
            date_default_timezone_set($oldTimezone);
        }
        /** @phpstan-var LOG_TEST $properties*/
        return $properties;
    }


    /**
     * Parse a string with order properties.
     *
     * @param  string $values - order property values from a log file
     *
     * @return         scalar[] - LOG_ORDER array with order properties
     * @phpstan-return LOG_ORDER
     *
     * @see \rosasurfer\rt\phpstan\LOG_ORDER
     */
    protected static function parseOrderProperties(string $values): array {
        $valuesOrig = $values;
        $values = trim($values);
        $properties = [];
                                                                                            // increase RegExp limit if needed
        static $pcreLimit = null; !$pcreLimit && $pcreLimit = ini_get_int('pcre.backtrack_limit');
        if (strlen($values) > $pcreLimit) PHP::ini_set('pcre.backtrack_limit', $pcreLimit=strlen($values));

        // order.0={id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment=""}
        if (!strStartsWith($values, 'order.')) throw new InvalidValueException('Unsupported order properties format: "'.$valuesOrig.'"');
        $values = trim(strRight($values, -6));

        // 0={id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment=""}
        $prefix = strLeftTo($values, '=');
        if (!strIsDigits($prefix)) throw new InvalidValueException('Unsupported order properties format: "'.$valuesOrig.'"');
        $values = trim(substr($values, strlen($prefix)+1));

        // {id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment=""}
        if (!strStartsWith($values, '{') || !strEndsWith($values, '}')) throw new InvalidValueException('Unsupported order properties format: "'.$valuesOrig.'"');
        $values = ', '.trim(substr($values, 1, strlen($values)-2)).', ';
        // ', id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment="", '

        // id
        $pattern = '/, *id *= *([0-9]+) *,/i';
        $matches = null;
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("id" invalid or not found): "'.$valuesOrig.'"');
        $properties['id'] = (int)$matches[1][0];
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "id" occurrences): "'.$valuesOrig.'"');

        // ticket
        $pattern = '/, *ticket *= *([0-9]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("ticket" invalid or not found): "'.$valuesOrig.'"');
        $properties['ticket'] = (int)$matches[1][0];
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "ticket" occurrences): "'.$valuesOrig.'"');

        // type
        $pattern = '/, *type *= *(OP_[^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("type" invalid or not found): "'.$valuesOrig.'"');
        if (($type = Rost::strToOrderType($matches[1][0])) < 0)            throw new InvalidValueException('Illegal order property "type": "'.$matches[1][0].'"');
        $properties['type'] = $type;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "type" occurrences): "'.$valuesOrig.'"');

        // lots
        $pattern = '/, *lots *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("lots" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($lots = $matches[1][0]))                         throw new InvalidValueException('Illegal order property "lots": "'.$matches[1][0].'"');
        $properties['lots'] = (float)$lots;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "lots" occurrences): "'.$valuesOrig.'"');

        // symbol
        $pattern = '/, *symbol *= *"([^" ]+)" *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("symbol" invalid or not found): "'.$valuesOrig.'"');
        $properties['symbol'] = $matches[1][0];
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "symbol" occurrences): "'.$valuesOrig.'"');

        // openPrice
        $pattern = '/, *openPrice *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("openPrice" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($price = $matches[1][0]))                        throw new InvalidValueException('Illegal order property "openPrice": "'.$matches[1][0].'"');
        $properties['openPrice'] = (float)$price;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "openPrice" occurrences): "'.$valuesOrig.'"');

        // openTime (FXT)
        $pattern = '/, *openTime *= *"([^"]+)" *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("openTime" invalid or not found): "'.$valuesOrig.'"');
        if (!$time = strtotime($matches[1][0]))                            throw new InvalidValueException('Illegal order property "openTime": "'.$matches[1][0].'"');
        $properties['openTime'] = $time;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "openTime" occurrences): "'.$valuesOrig.'"');

        // stopLoss
        $pattern = '/, *stopLoss *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("stopLoss" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($price = $matches[1][0]))                        throw new InvalidValueException('Illegal order property "stopLoss": "'.$matches[1][0].'"');
        $properties['stopLoss'] = (float)$price;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "stopLoss" occurrences): "'.$valuesOrig.'"');

        // takeProfit
        $pattern = '/, *takeProfit *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("takeProfit" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($price = $matches[1][0]))                        throw new InvalidValueException('Illegal order property "takeProfit": "'.$matches[1][0].'"');
        $properties['takeProfit'] = (float)$price;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "takeProfit" occurrences): "'.$valuesOrig.'"');

        // closePrice
        $pattern = '/, *closePrice *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("closePrice" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($price = $matches[1][0]))                        throw new InvalidValueException('Illegal order property "closePrice": "'.$matches[1][0].'"');
        $properties['closePrice'] = (float)$price;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "closePrice" occurrences): "'.$valuesOrig.'"');

        // closeTime (FXT)
        $pattern = '/, *closeTime *= *(0|"[^"]+") *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("closeTime" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsDoubleQuoted($time = $matches[1][0])) {
            if ($time != '0')                                              throw new InvalidValueException('Illegal order property "closeTime": "'.$matches[1][0].'"');
            $time = 0;
        }
        else if (!$time = strtotime(substr($time, 1, strlen($time)-2)))    throw new InvalidValueException('Illegal order property "closeTime": "'.$matches[1][0].'"');
        $properties['closeTime'] = $time;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "closeTime" occurrences): "'.$valuesOrig.'"');

        // commission
        $pattern = '/, *commission *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("commission" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($amount = $matches[1][0]))                       throw new InvalidValueException('Illegal order property "commission": "'.$matches[1][0].'"');
        $properties['commission'] = (float)$amount;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "commission" occurrences): "'.$valuesOrig.'"');

        // swap
        $pattern = '/, *swap *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("swap" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($amount = $matches[1][0]))                       throw new InvalidValueException('Illegal order property "swap": "'.$matches[1][0].'"');
        $properties['swap'] = (float)$amount;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "swap" occurrences): "'.$valuesOrig.'"');

        // profit
        $pattern = '/, *profit *= *([^ ]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("profit" invalid or not found): "'.$valuesOrig.'"');
        if (!strIsNumeric($amount = $matches[1][0]))                       throw new InvalidValueException('Illegal order property "profit": "'.$matches[1][0].'"');
        $properties['profit'] = (float)$amount;
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "profit" occurrences): "'.$valuesOrig.'"');

        // magicNumber
        $pattern = '/, *magicNumber *= *([0-9]+) *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("magicNumber" invalid or not found): "'.$valuesOrig.'"');
        $properties['magicNumber'] = (int)$matches[1][0];
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "magicNumber" occurrences): "'.$valuesOrig.'"');

        // comment
        $pattern = '/, *comment *= *"([^"]*)" *,/i';
        if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE)) throw new InvalidValueException('Illegal order properties ("comment" invalid or not found): "'.$valuesOrig.'"');
        $properties['comment'] = $matches[1][0];
        if (preg_match($pattern, $values, $matches, 0, $matches[0][1]+1))  throw new InvalidValueException('Illegal order properties (multiple "comment" occurrences): "'.$valuesOrig.'"');

        /** @phpstan-var LOG_ORDER $properties*/
        return $properties;
    }


    /**
     * Set the reporting id of the Test (used for composition of Test::reportingSymbol).
     *
     * @param  int $id - positive value
     *
     * @return $this
     */
    public function setReportingId(int $id): self {
        if ($id <= 0) throw new InvalidValueException('Invalid parameter $id: '.$id.' (not positive)');

        $this->reportingId = $id;
        $this->modified();
        return $this;
    }


    /**
     * {@inheritDoc}
     *
     * Make sure the test statistics are calculated.
     */
    protected function beforeInsert(): bool {
        $this->getStats();
        return true;
    }


    /**
     * {@inheritDoc}
     *
     * Insert the related entities as this is not yet automated by the ORM.
     */
    protected function afterInsert(): void {
        $objects = array_merge(
            $this->getStrategyParameters(),
            $this->getTrades(),
            [$this->getStats()]
        );
        foreach ($objects as $object) {
            $object->save();
        }
    }
}
