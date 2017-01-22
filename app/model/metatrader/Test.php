<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\dao\PersistableObject;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * Represents a test executed in the MetaTrader Strategy Tester.
 */
class Test extends PersistableObject {


   /**
    * Create a new Test instance from the provided data files.
    *
    * @param  string $configurationFile - name of the test's configuration file
    * @param  string $resultsFile       - name of the test's results file
    *
    * @return self
    */
   public static function create($configurationFile, $resultsFile) {
      if (!is_string($configurationFile)) throw new IllegalTypeException('Illegal type of parameter $configurationFile: '.getType($configurationFile));
      if (!is_file($configurationFile))   throw new InvalidArgumentException('File not found: "'.$configurationFile.'"');
      if (!is_string($resultsFile))       throw new IllegalTypeException('Illegal type of parameter $resultsFile: '.getType($resultsFile));
      if (!is_file($resultsFile))         throw new InvalidArgumentException('File not found: "'.$resultsFile.'"');

      $test = new static();

      // parse files

      // assign properties

      /*
      $test->ticket      =               $data['ticket'     ];
      $test->type        =               $data['type'       ];
      $test->lots        =               $data['lots'       ];
      $test->symbol      =               $data['symbol'     ];
      $test->openTime    = MyFX::fxtDate($data['opentime'   ]);
      $test->openPrice   =               $data['openprice'  ];
      $test->closeTime   = MyFX::fxtDate($data['closetime'  ]);
      $test->closePrice  =               $data['closeprice' ];
      $test->stopLoss    =         isSet($data['stoploss'   ]) ? $data['stoploss'   ] : null;
      $test->takeProfit  =         isSet($data['takeprofit' ]) ? $data['takeprofit' ] : null;
      $test->commission  =         isSet($data['commission' ]) ? $data['commission' ] : null;
      $test->swap        =         isSet($data['swap'       ]) ? $data['swap'       ] : null;
      $test->grossProfit =         isSet($data['grossprofit']) ? $data['grossprofit'] : null;
      $test->netProfit   =               $data['netprofit'  ];
      $test->magicNumber =         isSet($data['magicnumber']) ? $data['magicnumber'] : null;
      $test->comment     =         isSet($data['comment'    ]) ? $data['comment'    ] : null;
      $test->signal_id   = $signal->getId();
      */
      return $test;
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
