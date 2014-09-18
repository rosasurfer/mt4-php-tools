<?
/**
 * ClosedPosition
 */
class ClosedPosition extends PersistableObject {


   protected /*int*/    $ticket;
   protected /*string*/ $type;
   protected /*float*/  $lots;
   protected /*string*/ $symbol;
   protected /*string*/ $openTime;
   protected /*float*/  $openPrice;
   protected /*string*/ $closeTime;
   protected /*float*/  $closePrice;
   protected /*float*/  $stopLoss;
   protected /*float*/  $takeProfit;
   protected /*float*/  $commission;
   protected /*float*/  $swap;
   protected /*float*/  $profit;
   protected /*int*/    $magicNumber;
   protected /*string*/ $comment;
   protected /*int*/    $signal_id;

   private   /*Signal*/ $signal;


   // Getter
   public function getTicket()      { return $this->ticket;      }
   public function getType()        { return $this->type;        }
   public function getLots()        { return $this->lots;        }
   public function getSymbol()      { return $this->symbol;      }
   public function getOpenTime()    { return $this->openTime;    }
   public function getOpenPrice()   { return $this->openPrice;   }
   public function getCloseTime()   { return $this->closeTime;   }
   public function getClosePrice()  { return $this->closePrice;  }
   public function getStopLoss()    { return $this->stopLoss;    }
   public function getTakeProfit()  { return $this->takeProfit;  }
   public function getCommission()  { return $this->commission;  }
   public function getSwap()        { return $this->swap;        }
   public function getProfit()      { return $this->profit;      }
   public function getMagicNumber() { return $this->magicNumber; }
   public function getComment()     { return $this->comment;     }
   public function getSignal_id()   { return $this->signal_id;   }


   /**
    * Erzeugt eine neue geschlossene Position mit den angegebenen Daten.
    *
    * @param  string $signalAlias - Alias des Signals der Position
    * @param  array  $data        - Positionsdaten
    *
    * @return ClosedPosition
    */
   public static function create($signalAlias, array $data) {
      if (!is_string($signalAlias)) throw new IllegalTypeException('Illegal type of parameter $signalAlias: '.getType($signalAlias));

      $position = new self();

      $position->ticket      =                $data['ticket'     ];
      $position->type        =                $data['type'       ];
      $position->lots        =                $data['lots'       ];
      $position->symbol      =                $data['symbol'     ];
      $position->openTime    = MyFX ::fxtDate($data['opentime'   ]);
      $position->openPrice   =                $data['openprice'  ];
      $position->closeTime   = MyFX ::fxtDate($data['closetime'  ]);
      $position->closePrice  =                $data['closeprice' ];
      $position->stopLoss    =          isSet($data['stoploss'   ]) ? $data['stoploss'   ] : null;
      $position->takeProfit  =          isSet($data['takeprofit' ]) ? $data['takeprofit' ] : null;
      $position->commission  =                $data['commission' ];
      $position->swap        =                $data['swap'       ];
      $position->profit      =                $data['profit'     ];
      $position->magicNumber =          isSet($data['magicnumber']) ? $data['magicnumbe' ] : null;
      $position->comment     =          isSet($data['comment'    ]) ? $data['comment'    ] : null;

      $position->signal_id = Signal ::dao()->getIdByAlias($signalAlias);
      if (!$position->signal_id) throw new plInvalidArgumentException('Invalid signal alias "'.$signalAlias.'"');

      return $position;
   }


   /**
    * Gibt den DAO für diese Klasse zurück.
    *
    * @return CommonDAO
    */
   public static function dao() {
      return self ::getDAO(__CLASS__);
   }


   /**
    * Fügt diese Instanz in die Datenbank ein.
    *
    * @return ClosedPosition
    */
   protected function insert() {
      $created = $this->created;
      $version = $this->version;

      $ticket      =  $this->ticket;
      $type        =  $this->type;
      $lots        =  $this->lots;
      $symbol      =  $this->symbol;
      $opentime    =  $this->openTime;
      $openprice   =  $this->openPrice;
      $closetime   =  $this->closeTime;
      $closeprice  =  $this->closePrice;
      $stoploss    = !$this->stopLoss          ? 'null' : $this->stopLoss;
      $takeprofit  = !$this->takeProfit        ? 'null' : $this->takeProfit;
      $commission  =  $this->commission;
      $swap        =  $this->swap;
      $profit      =  $this->profit;
      $magicnumber = !$this->magicNumber       ? 'null' : $this->magicNumber;
      $comment     = ($this->comment === null) ? 'null' : addSlashes($this->comment);
      $signal_id   =  $this->signal_id;

      $db = self ::dao()->getDB();
      $db->begin();
      try {
         // ClosedPosition einfügen
         $sql = "insert into t_closedposition (version, created, ticket, type, lots, symbol, opentime, openprice, closetime, closeprice, stoploss, takeprofit, commission, swap, profit, magicnumber, comment, signal_id) values
                    ('$version', '$created', $ticket, '$type', $lots, '$symbol', '$opentime', $openprice, '$closetime', $closeprice, $stoploss, $takeprofit, $commission, $swap, $profit, $magicnumber, '$comment', $signal_id)";
         $sql = str_replace("'null'", 'null', $sql);
         $db->executeSql($sql);
         $result = $db->executeSql("select last_insert_id()");
         $this->id = (int) mysql_result($result['set'], 0);

         $db->commit();
      }
      catch (Exception $ex) {
         $this->id = null;
         $db->rollback();
         throw $ex;
      }
      return $this;
   }
}
?>
