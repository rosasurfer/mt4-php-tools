<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\dao\PersistableObject;
use rosasurfer\exception\IllegalArgumentException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\util\PHP;


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

   /** @var TestTrade[] - trades of the test */
   protected $trades;


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
      $propertiesDone = false;
      $i = 0;
      while (($line=fGets($hFile)) !== false) {
         $i++;
         if (!strLen($line=trim($line)))
            continue;

         if (!$propertiesDone) {
            // first line: test properties
            $properties = self::parseLoggedProperties($line);
            // assign properties
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
            $propertiesDone = true;
            continue;
         }

         // all further lines: trades
         if (!strStartsWith($line, 'order.')) throw new InvalidArgumentException('Unsupported file format in line '.$i.' of "'.$resultsFile.'"');
         $test->trades[] = $line;
         //$test->trades[] = new TestTrade($test, $line);
      }
      fClose($hFile);

      // (2) parse the config file
      /*
      config:  uint tradeDirections;                        //      4     enabled trade directions: Long|Short|Both
      config:  input params
      */
      return $test;
   }


   /**
    * Parse a log entry with test properties.
    *
    * @param  string $values - property values from a log file
    *
    * @return mixed[] - associative array with parsed property values
    */
   private static function parseLoggedProperties($values) {
      if (!is_string($values)) throw new IllegalTypeException('Illegal type of parameter $values: '.getType($values));
      $valuesOrig = $values;
      $values     = trim($values);
      $properties = [];

      // test={id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", tickModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451}
      if (!strStartsWith($values, 'test=')) throw new InvalidArgumentException('Unsupported test properties format: "'.$valuesOrig.'"');
      $values = trim(strRight($values, -5));

      // {id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", tickModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451}
      if (!strStartsWith($values, '{') || !strEndsWith($values, '}')) throw new InvalidArgumentException('Unsupported test properties format: "'.$valuesOrig.'"');
      $values = trim(subStr($values, 1, strLen($values)-2));
      // id=0, time="Tue, 10-Jan-2017 23:36:38", strategy="MyFX Example MA", reportingId=2, reportingSymbol="MyFXExa.002", symbol="EURUSD", timeframe=PERIOD_M1, startTime="Tue, 01-Dec-2015 00:03:00", endTime="Thu, 31-Dec-2015 23:58:59", tickModel=0, spread=0.1, bars=31535, ticks=31536, accountDeposit=100000.00, accountCurrency="USD", tradeDirections=0, visualMode=FALSE, duration=1.544 s, orders=1451

      // id
      $pattern = '/(?:^|,) *id *= *([0-9]+) *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("id" not found): "'.$valuesOrig.'"');
      $properties['id'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "id" occurrences): "'.$valuesOrig.'"');

      // created
      $pattern = '/(?:^|,) *time *= *"([^"]+)" *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("time" not found): "'.$valuesOrig.'"');
      $properties['created'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "time" occurrences): "'.$valuesOrig.'"');

      // strategy
      $pattern = '/(?:^|,) *strategy *= *"([^"]+)" *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("strategy" not found): "'.$valuesOrig.'"');
      $properties['strategy'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "strategy" occurrences): "'.$valuesOrig.'"');

      // reportingId
      $pattern = '/(?:^|,) *reportingId *= *([0-9]+) *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("reportingId" not found): "'.$valuesOrig.'"');
      $properties['reportingId'] = (int)$matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "reportingId" occurrences): "'.$valuesOrig.'"');

      // reportingSymbol
      $pattern = '/(?:^|,) *reportingSymbol *= *"([^"]+)" *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("reportingSymbol" not found): "'.$valuesOrig.'"');
      $properties['reportingSymbol'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "reportingSymbol" occurrences): "'.$valuesOrig.'"');

      // symbol
      $pattern = '/(?:^|,) *symbol *= *"([^"]+)" *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("symbol" not found): "'.$valuesOrig.'"');
      $properties['symbol'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "symbol" occurrences): "'.$valuesOrig.'"');

      // timeframe
      $pattern = '/(?:^|,) *timeframe *= *(PERIOD_[A-Z0-9]+) *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("timeframe" not found): "'.$valuesOrig.'"');
      if (!$properties['timeframe']=\MT4::strToTimeframe($matches[1][0]))  throw new IllegalArgumentException('Illegal "timeframe" property: '.$matches[1][0]);
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "timeframe" occurrences): "'.$valuesOrig.'"');

      // startTime
      $pattern = '/(?:^|,) *startTime *= *"([^"]+)" *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("startTime" not found): "'.$valuesOrig.'"');
      $properties['startTime'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "startTime" occurrences): "'.$valuesOrig.'"');

      // endTime
      $pattern = '/(?:^|,) *endTime *= *"([^"]+)" *(?:,|$)/i';
      if (!preg_match($pattern, $values, $matches, PREG_OFFSET_CAPTURE))   throw new IllegalArgumentException('Illegal test properties ("endTime" not found): "'.$valuesOrig.'"');
      $properties['endTime'] = $matches[1][0];
      if (preg_match($pattern, $values, $matches, null, $matches[0][1]+1)) throw new IllegalArgumentException('Illegal test properties (multiple "endTime" occurrences): "'.$valuesOrig.'"');


      echoPre($properties);
      /*
      ?        uint          tickModel;                              //      4     used tick model: 0=EveryTick|1=ControlPoints|2=BarOpen
      results: double        spread;                                 //      8     spread in pips
      results: uint          bars;                                   //      4     number of tested bars
      results: uint          ticks;                                  //      4     number of tested ticks
      results: BOOL          visualMode;                             //      4     whether or not the test was run in visual mode
      results: uint          duration;                               //      4     test duration in milliseconds
      */
      return [];
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
}
