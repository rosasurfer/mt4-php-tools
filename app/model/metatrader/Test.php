<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\dao\PersistableObject;
use rosasurfer\exception\IllegalArgumentException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\util\PHP;
use function rosasurfer\strIsDigits;


/**
 * Represents a test executed in the MetaTrader Strategy Tester.
 */
class Test extends PersistableObject {

   /** @var string - time of the test */
   protected $time;

   /** @var string - strategy name */
   protected $strategy;

   /** @var int - reporting id (for composition of reportingSymbol) */
   protected $reportingId;

   /** @var string - test symbol for charted reports */
   protected $reportingSymbol;

   /** @var string - tested symbol */
   protected $symbol;

   /** @var int - tested timeframe */
   protected $timeframe;

   /** @var string - time of the first tick of testing */
   protected $startTime;

   /** @var string - time of the last tick of testing */
   protected $endTime;

   /** @var int - used tick model: EveryTick|ControlPoints|BarOpen */
   protected $tickModel;

   /** @var double - spread in pips */
   protected $spread;

   /** @var int - number of tested bars */
   protected $bars;

   /** @var int - number of tested ticks */
   protected $ticks;

   /** @var int - enabled trade directions: Long|Short|Both */
   protected $tradeDirections;

   /** @var bool - whether or not the test was run in visual mode */
   protected $visualMode;

   /** @var int - test duration in milliseconds */
   protected $duration;

   /** @var Order[] - trade history of the test */
   protected $history;


   /**
    * Create a new Test instance from the provided data files.
    *
    * @param  string $configFile  - name of the test's configuration file
    * @param  string $resultsFile - name of the test's results file
    *
    * @return self
    */
   public static function create($configFile, $resultsFile) {
      if (!is_string($configFile))  throw new IllegalTypeException('Illegal type of parameter $configFile: '.getType($configFile));
      if (!is_string($resultsFile)) throw new IllegalTypeException('Illegal type of parameter $resultsFile: '.getType($resultsFile));
      PHP::ini_set('auto_detect_line_endings', 1);

      $test = new static();

      // (1) parse the results file
      $hFile = fOpen($resultsFile, 'rb');
      $i = 0;
      while (($line=fGets($hFile)) !== false) {
         $i++;
         if (!strLen($line=trim($line)))
            continue;

         if (!$test->reportingId) {
            // first line: test properties
            $properties = self::parseProperties($line);

            // assign Test properties
            /*
            results: datetime      time;                                   //      4     time of the test
            results: char          strategy[MAX_PATH];                     //    260     strategy name
            results: int           reportingId;                            //      4     reporting id (for composition of reportingSymbol)
            results: char          reportingSymbol[MAX_SYMBOL_LENGTH+1];   //     12     test symbol for charted reports
            results: char          symbol         [MAX_SYMBOL_LENGTH+1];   //     12     tested symbol
            results: uint          timeframe;                              //      4     tested timeframe
            results: datetime      startTime;                              //      4     time of the first tick of testing
            results: datetime      endTime;                                //      4     time of the last tick of testing
            ?        uint          tickModel;                              //      4     used tick model: 0=EveryTick|1=ControlPoints|2=BarOpen
            results: double        spread;                                 //      8     spread in pips
            results: uint          bars;                                   //      4     number of tested bars
            results: uint          ticks;                                  //      4     number of tested ticks
            results: BOOL          visualMode;                             //      4     whether or not the test was run in visual mode
            results: uint          duration;                               //      4     test duration in milliseconds
            */

            echoPre($properties);
            $test->setReportingId($properties['reportingId']);
            continue;
         }

         // all further lines: trades
         if (!strStartsWith($line, 'order.')) throw new InvalidArgumentException('Unsupported file format in line '.$i.' of "'.$resultsFile.'"');
         $properties = self::parseOrder($line);
         $test->history[] = Order::create($test, $properties);
      }
      echoPre('sizeOf($test->history) = '.sizeof($test->history));
      echoPre($properties);
      fClose($hFile);


      // (2) parse the config file
      /*
      config:  uint tradeDirections;                        //      4     enabled trade directions: Long|Short|Both
      config:  input params
      */
      return $test;
   }


   /**
    * Parse a string with test properties.
    *
    * @param  string $values - test property values from a log file
    *
    * @return mixed[] - associative array with parsed properties
    */
   private static function parseProperties($values) {
      if (!is_string($values)) throw new IllegalTypeException('Illegal type of parameter $values: '.getType($values));
      $valuesOrig = $values;
      $values     = trim($values);
      $properties = [];

      // TODO: convert datetime strings to timestamp (timezone!)

      // test={id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", tickModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451}
      if (!strStartsWith($values, 'test=')) throw new InvalidArgumentException('Unsupported test properties format: "'.$valuesOrig.'"');
      $values = trim(strRight($values, -5));

      // {id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", tickModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451}
      if (!strStartsWith($values, '{') || !strEndsWith($values, '}')) throw new InvalidArgumentException('Unsupported test properties format: "'.$valuesOrig.'"');
      $values = ', '.trim(subStr($values, 1, strLen($values)-2)).', ';
      // ', id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", tickModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451, '

      // id
      $pattern = '/, *id *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("id" invalid or not found): "'.$valuesOrig.'"');
      $properties['id'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "id" occurrences): "'.$valuesOrig.'"');

      // time
      $pattern = '/, *time *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("time" invalid or not found): "'.$valuesOrig.'"');
      $properties['time'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "time" occurrences): "'.$valuesOrig.'"');

      // strategy
      $pattern = '/, *strategy *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("strategy" invalid or not found): "'.$valuesOrig.'"');
      $properties['strategy'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "strategy" occurrences): "'.$valuesOrig.'"');

      // reportingId
      $pattern = '/, *reportingId *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("reportingId" invalid or not found): "'.$valuesOrig.'"');
      $properties['reportingId'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "reportingId" occurrences): "'.$valuesOrig.'"');

      // reportingSymbol
      $pattern = '/, *reportingSymbol *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("reportingSymbol" invalid or not found): "'.$valuesOrig.'"');
      $properties['reportingSymbol'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "reportingSymbol" occurrences): "'.$valuesOrig.'"');

      // symbol
      $pattern = '/, *symbol *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("symbol" invalid or not found): "'.$valuesOrig.'"');
      $properties['symbol'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "symbol" occurrences): "'.$valuesOrig.'"');

      // timeframe
      $pattern = '/, *timeframe *= *(PERIOD_[^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("timeframe" invalid or not found): "'.$valuesOrig.'"');
      if (!$id = \MT4::strToTimeframe($matches[1][0]))                     throw new IllegalArgumentException('Illegal test "timeframe" property: '.$matches[1][0]);
      $properties['timeframe'] = $id;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "timeframe" occurrences): "'.$valuesOrig.'"');

      // startTime
      $pattern = '/, *startTime *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("startTime" invalid or not found): "'.$valuesOrig.'"');
      $properties['startTime'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "startTime" occurrences): "'.$valuesOrig.'"');

      // endTime
      $pattern = '/, *endTime *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("endTime" invalid or not found): "'.$valuesOrig.'"');
      $properties['endTime'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "endTime" occurrences): "'.$valuesOrig.'"');

      // tickModel
      $pattern = '/, *tickModel *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("tickModel" invalid or not found): "'.$valuesOrig.'"');
      if (($id = \MT4::strToTickModel($matches[1][0])) < 0)                throw new IllegalArgumentException('Illegal test "tickModel" property: '.$matches[1][0]);
      $properties['tickModel'] = $id;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "tickModel" occurrences): "'.$valuesOrig.'"');

      // spread
      $pattern = '/, *spread *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("spread" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($spread = $matches[1][0]))                         throw new IllegalArgumentException('Illegal test "spread" property: '.$matches[1][0]);
      $properties['spread'] = (double)$spread;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "spread" occurrences): "'.$valuesOrig.'"');

      // bars
      $pattern = '/, *bars *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("bars" invalid or not found): "'.$valuesOrig.'"');
      $properties['bars'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "bars" occurrences): "'.$valuesOrig.'"');

      // ticks
      $pattern = '/, *ticks *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("ticks" invalid or not found): "'.$valuesOrig.'"');
      $properties['ticks'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "ticks" occurrences): "'.$valuesOrig.'"');

      // visualMode
      $pattern = '/, *visualMode *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("visualMode" invalid or not found): "'.$valuesOrig.'"');
      if (is_null($mode = strToBool($matches[1][0])))                      throw new IllegalArgumentException('Illegal test "visualMode" property: '.$matches[1][0]);
      $properties['visualMode'] = (int)$mode;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "visualMode" occurrences): "'.$valuesOrig.'"');

      // duration
      $pattern = '/, *duration *= *([^ ]+) *s? *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("duration" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($duration = $matches[1][0]))                       throw new IllegalArgumentException('Illegal test "duration" property: '.$matches[1][0]);
      $properties['duration'] = (int)round($duration * 1000);
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "duration" occurrences): "'.$valuesOrig.'"');

      return $properties;
   }


   /**
    * Parse a string with order properties.
    *
    * @param  string $values - order property values from a log file
    *
    * @return mixed[] - associative array with parsed properties
    */
   private static function parseOrder($values) {
      if (!is_string($values)) throw new IllegalTypeException('Illegal type of parameter $values: '.getType($values));
      $valuesOrig = $values;
      $values     = trim($values);
      $properties = [];

      // TODO: convert datetime strings to timestamp (timezone!)

      // order.0={id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment=""}
      if (!strStartsWith($values, 'order.')) throw new InvalidArgumentException('Unsupported order properties format: "'.$valuesOrig.'"');
      $values = trim(strRight($values, -6));

      // 0={id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment=""}
      $prefix = strLeftTo($values, '=', 1, false, null);
      if (!strIsDigits($prefix)) throw new InvalidArgumentException('Unsupported order properties format: "'.$valuesOrig.'"');
      $values = trim(substr($values, strLen($prefix)+1));

      // {id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment=""}
      if (!strStartsWith($values, '{') || !strEndsWith($values, '}')) throw new InvalidArgumentException('Unsupported order properties format: "'.$valuesOrig.'"');
      $values = ', '.trim(subStr($values, 1, strLen($values)-2)).', ';
      // ', id=0, ticket=1, type=OP_SELL, lots=0.10, symbol="EURUSD", openPrice=1.05669, openTime="Tue, 01-Dec-2015 00:22:00", stopLoss=0, takeProfit=0, closePrice=1.05685, closeTime="Tue, 01-Dec-2015 00:29:00", commission=-0.43, swap=0.00, profit=-1.60, magicNumber=0, comment="", '

      // id
      $pattern = '/, *id *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("id" invalid or not found): "'.$valuesOrig.'"');
      $properties['id'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "id" occurrences): "'.$valuesOrig.'"');

      // ticket
      $pattern = '/, *ticket *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("ticket" invalid or not found): "'.$valuesOrig.'"');
      $properties['ticket'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "ticket" occurrences): "'.$valuesOrig.'"');

      // type
      $pattern = '/, *type *= *(OP_[^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("type" invalid or not found): "'.$valuesOrig.'"');
      if (($type = \MT4::strToOrderType($matches[1][0])) < 0)              throw new IllegalArgumentException('Illegal order "type" property: '.$matches[1][0]);
      $properties['type'] = $type;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "type" occurrences): "'.$valuesOrig.'"');

      // lots
      $pattern = '/, *lots *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("lots" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($lots = $matches[1][0]))                           throw new IllegalArgumentException('Illegal order "lots" property: '.$matches[1][0]);
      $properties['lots'] = (float)$lots;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "lots" occurrences): "'.$valuesOrig.'"');

      // symbol
      $pattern = '/, *symbol *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("symbol" invalid or not found): "'.$valuesOrig.'"');
      $properties['symbol'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "symbol" occurrences): "'.$valuesOrig.'"');

      // openPrice
      $pattern = '/, *openPrice *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("openPrice" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($price = $matches[1][0]))                          throw new IllegalArgumentException('Illegal order "openPrice" property: '.$matches[1][0]);
      $properties['openPrice'] = (float)$price;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "openPrice" occurrences): "'.$valuesOrig.'"');

      // openTime
      $pattern = '/, *openTime *= *"([^"]+)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("openTime" invalid or not found): "'.$valuesOrig.'"');
      $properties['openTime'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "openTime" occurrences): "'.$valuesOrig.'"');

      // stopLoss
      $pattern = '/, *stopLoss *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("stopLoss" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($price = $matches[1][0]))                          throw new IllegalArgumentException('Illegal order "stopLoss" property: '.$matches[1][0]);
      $properties['stopLoss'] = (float)$price;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "stopLoss" occurrences): "'.$valuesOrig.'"');

      // takeProfit
      $pattern = '/, *takeProfit *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("takeProfit" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($price = $matches[1][0]))                          throw new IllegalArgumentException('Illegal order "takeProfit" property: '.$matches[1][0]);
      $properties['takeProfit'] = (float)$price;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "takeProfit" occurrences): "'.$valuesOrig.'"');

      // closePrice
      $pattern = '/, *closePrice *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("closePrice" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($price = $matches[1][0]))                          throw new IllegalArgumentException('Illegal order "closePrice" property: '.$matches[1][0]);
      $properties['closePrice'] = (float)$price;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "closePrice" occurrences): "'.$valuesOrig.'"');

      // closeTime
      $pattern = '/, *closeTime *= *(0|"[^"]+") *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("closeTime" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsDoubleQuoted($time = $matches[1][0])) {
         if ($time != '0')                                                 throw new IllegalArgumentException('Illegal order "closePrice" property: '.$matches[1][0]);
         $time = null;
      }
      $properties['closeTime'] = $time;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "closeTime" occurrences): "'.$valuesOrig.'"');

      // commission
      $pattern = '/, *commission *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("commission" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($amount = $matches[1][0]))                         throw new IllegalArgumentException('Illegal order "commission" property: '.$matches[1][0]);
      $properties['commission'] = (float)$amount;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "commission" occurrences): "'.$valuesOrig.'"');

      // swap
      $pattern = '/, *swap *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("swap" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($amount = $matches[1][0]))                         throw new IllegalArgumentException('Illegal order "swap" property: '.$matches[1][0]);
      $properties['swap'] = (float)$amount;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "swap" occurrences): "'.$valuesOrig.'"');

      // profit
      $pattern = '/, *profit *= *([^ ]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("profit" invalid or not found): "'.$valuesOrig.'"');
      if (!strIsNumeric($amount = $matches[1][0]))                         throw new IllegalArgumentException('Illegal order "profit" property: '.$matches[1][0]);
      $properties['profit'] = (float)$amount;
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "profit" occurrences): "'.$valuesOrig.'"');

      // magicNumber
      $pattern = '/, *magicNumber *= *([0-9]+) *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("magicNumber" invalid or not found): "'.$valuesOrig.'"');
      $properties['magicNumber'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "magicNumber" occurrences): "'.$valuesOrig.'"');

      // comment
      $pattern = '/, *comment *= *"([^"]*)" *,/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal order properties ("comment" invalid or not found): "'.$valuesOrig.'"');
      $properties['comment'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal order properties (multiple "comment" occurrences): "'.$valuesOrig.'"');

      return $properties;
   }


   /**
    * Insert this instance into the database.
    *
    * @return self
    */
   protected function insert() {

      // create SQL

      // execute SQL

      // query instance id

      /*
      $created     =  $this->created;
      $ticket      =  $this->ticket;
      $closeprice  =  $this->closePrice;
      $stoploss    = !$this->stopLoss             ? 'null' : $this->stopLoss;
      $takeprofit  = !$this->takeProfit           ? 'null' : $this->takeProfit;
      $commission  =  is_null($this->commission ) ? 'null' : $this->commission;
      $swap        =  is_null($this->swap       ) ? 'null' : $this->swap;
      $profit      =  is_null($this->grossProfit) ? 'null' : $this->grossProfit;
      $magicnumber = !$this->magicNumber          ? 'null' : $this->magicNumber;
      $comment     =  is_null($this->comment)     ? 'null' : "'".addSlashes($this->comment)."'";

      $db = self::dao()->getDB();
      $db->begin();
      try {
         // insert instance
         $sql = "insert into t_closedposition (version, created, ticket, type, lots, symbol, opentime, openprice, closetime, closeprice, stoploss, takeprofit, commission, swap, profit, netprofit, magicnumber, comment, signal_id) values
                    ('$version', '$created', $ticket, '$type', $lots, '$symbol', '$opentime', $openprice, '$closetime', $closeprice, $stoploss, $takeprofit, $commission, $swap, $profit, $netprofit, $magicnumber, $comment, $signal_id)";
         $db->executeSql($sql);
         $result = $db->executeSql("select last_insert_id()");
         $this->id = (int) mysql_result($result['set'], 0);

         $db->commit();
      }
      catch (\Exception $ex) {
         $this->id = null;
         $db->rollback();
         throw $ex;
      }
      */
      return $this;
   }


   /**
    * Set the reporting id of the Test (composition of reportingSymbol).
    *
    * @param  int $id - positive value
    *
    * @return self
    */
   public function setReportingId($id) {
      if (!is_int($id)) throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
      if ($id <= 0)     throw new InvalidArgumentException('Invalid parameter $id: '.$id.' (not positive)');

      $this->reportingId = $id;
      $this->isPersistent() && $this->modified = true;
      return $this;
   }
}
