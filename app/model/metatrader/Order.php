<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\dao\PersistableObject;


/**
 * Represents a MetaTrader order.
 */
class Order extends PersistableObject {

   /** @var int - ticket*/
   protected $ticket;

   /** @var int - order type */
   protected $type;

   /** @var float - lot size */
   protected $lots;

   /** @var string - symbol */
   protected $symbol;

   /** @var float - open price */
   protected $openPrice;

   /** @var string - open time (server timezone) */
   protected $openTime;

   /** @var float - stoploss price */
   protected $stopLoss;

   /** @var float - takeprofit price */
   protected $takeProfit;

   /** @var float - close price */
   protected $closePrice;

   /** @var string - close time (server timezone) */
   protected $closeTime;

   /** @var float - commission */
   protected $commission;

   /** @var float - swap */
   protected $swap;

   /** @var float - gross profit */
   protected $profit;

   /** @var int - magic number */
   protected $magicNumber;

   /** @var string - order comment */
   protected $comment;


   /**
    * Create a new Order.
    *
    * @return self
    */
   public static function create(Test $test, array $properties) {
      $order = new static();

      // assign Order properties
      /*
      uint     id;                                       // unique order id (positive, primary key)
      int      ticket;
      int      type;
      double   lots;
      char     symbol[MAX_SYMBOL_LENGTH+1];
      double   openPrice;
      datetime openTime;
      double   stopLoss;
      double   takeProfit;
      double   closePrice;
      datetime closeTime;
      double   commission;
      double   swap;
      double   profit;
      int      magicNumber;
      char     comment[MAX_ORDER_COMMENT_LENGTH+1];
      */
      return $order;
   }


   /**
    * Insert the instance into the database.
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
