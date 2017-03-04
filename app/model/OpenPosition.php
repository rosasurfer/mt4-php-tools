<?php
use rosasurfer\db\orm\PersistableObject;

use rosasurfer\exception\ConcurrentModificationException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use rosasurfer\util\Date;


/**
 * OpenPosition
 */
class OpenPosition extends PersistableObject {


   /** @var int - primary key */
   protected $id;

   /** @var (string)datetime - time of creation */
   protected $created;

   protected /*int   */ $ticket;
   protected /*string*/ $type;
   protected /*float */ $lots;
   protected /*string*/ $symbol;
   protected /*string*/ $openTime;
   protected /*float */ $openPrice;
   protected /*float */ $stopLoss;
   protected /*float */ $takeProfit;
   protected /*float */ $commission;
   protected /*float */ $swap;
   protected /*int   */ $magicNumber;
   protected /*string*/ $comment;
   protected /*int   */ $signal_id;

   private   /*Signal*/ $signal;


   // Getter
   public function getId()          { return $this->id;          }
   public function getTicket()      { return $this->ticket;      }
   public function getType()        { return $this->type;        }
   public function getLots()        { return $this->lots;        }
   public function getSymbol()      { return $this->symbol;      }
   public function getOpenPrice()   { return $this->openPrice;   }
   public function getStopLoss()    { return $this->stopLoss;    }
   public function getTakeProfit()  { return $this->takeProfit;  }
   public function getMagicNumber() { return $this->magicNumber; }
   public function getComment()     { return $this->comment;     }
   public function getSignal_id()   { return $this->signal_id;   }


   /**
    * Erzeugt eine neue offene Position mit den angegebenen Daten.
    *
    * @param  Signal $signal - Signal, zu dem die Position gehört
    * @param  array  $data   - Positionsdaten
    *
    * @return self
    */
   public static function create(Signal $signal, array $data) {
      if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process '.__CLASS__.' for non-persistent '.get_class($signal));

      $position = new static();

      $position->ticket      =                $data['ticket'     ];
      $position->type        =                $data['type'       ];
      $position->lots        =                $data['lots'       ];
      $position->symbol      =                $data['symbol'     ];
      $position->openTime    = \MyFX::fxtDate($data['opentime'   ]);
      $position->openPrice   =                $data['openprice'  ];
      $position->stopLoss    =          isSet($data['stoploss'   ]) ? $data['stoploss'   ] : null;
      $position->takeProfit  =          isSet($data['takeprofit' ]) ? $data['takeprofit' ] : null;
      $position->commission  =          isSet($data['commission' ]) ? $data['commission' ] : null;
      $position->swap        =          isSet($data['swap'       ]) ? $data['swap'       ] : null;
      $position->magicNumber =          isSet($data['magicnumber']) ? $data['magicnumber'] : null;
      $position->comment     =          isSet($data['comment'    ]) ? $data['comment'    ] : null;
      $position->signal_id   = $signal->getId();

      return $position;
   }


   /**
    * Return the creation time of the instance.
    *
    * @param  string $format - format as used by date($format, $timestamp)
    *
    * @return string - creation time
    */
   public function getCreated($format = 'Y-m-d H:i:s')  {
      if ($format == 'Y-m-d H:i:s')
         return $this->created;
      return Date::format($this->created, $format);
   }


   /**
    * Gibt die Beschreibung des OperationTypes dieser Position zurück.
    *
    * @return string - Beschreibung
    */
   public function getTypeDescription() {
      return ucFirst($this->type);
   }


   /**
    * Gibt die OpenTime dieser Position zurück.
    *
    * @param  string $format - Zeitformat (default: 'Y-m-d H:i:s')
    *
    * @return string - Zeitpunkt
    */
   public function getOpenTime($format='Y-m-d H:i:s') {
      if ($format == 'Y-m-d H:i:s')
         return $this->openTime;
      return Date::format($this->openTime, $format);
   }


   /**
    * Gibt den Commission-Betrag dieser Position zurück.
    *
    * @param  int    $decimals  - Anzahl der Nachkommastellen
    * @param  string $separator - Dezimaltrennzeichen
    *
    * @return float|string - Betrag oder NULL, wenn der Betrag nicht verfügbar ist
    */
   public function getCommission($decimals=2, $separator='.') {
      if (is_null($this->commission) || !func_num_args())
         return $this->commission;
      return Number::format($this->commission, $decimals, $separator);
   }


   /**
    * Gibt den Swap-Betrag dieser Position zurück.
    *
    * @param  int    $decimals  - Anzahl der Nachkommastellen
    * @param  string $separator - Dezimaltrennzeichen
    *
    * @return float|string - Betrag oder NULL, wenn der Betrag nicht verfügbar ist
    */
   public function getSwap($decimals=2, $separator='.') {
      if (is_null($this->swap) || !func_num_args())
         return $this->swap;
      return Number::format($this->swap, $decimals, $separator);
   }


   /**
    * Setzt den StopLoss dieser Position auf den angegebenen Wert.
    *
    * @param  float $value - StopLoss-Value (0 oder NULL löschen den aktuellen Wert)
    *
    * @return Customer
    */
   public function setStopLoss($value) {
      if (!is_null($value) && !is_int($value) && !is_float($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
      if ($value < 0)                                               throw new InvalidArgumentException('Invalid StopLoss value '.$value);

      if (!$value)
         $value = null;

      if ($value !== $this->stopLoss) {
         $this->stopLoss = $value;

         $this->isPersistent() && $this->modified=true;
      }
      return $this;
   }


   /**
    * Setzt den TakeProfit dieser Position auf den angegebenen Wert.
    *
    * @param  float $value - TakeProfit-Value (0 oder NULL löschen den aktuellen Wert)
    *
    * @return Customer
    */
   public function setTakeProfit($value) {
      if (!is_null($value) && !is_int($value) && !is_float($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
      if ($value < 0)                                               throw new InvalidArgumentException('Invalid TakeProfit value '.$value);

      if (!$value)
         $value = null;

      if ($value !== $this->takeProfit) {
         $this->takeProfit = $value;

         $this->isPersistent() && $this->modified=true;
      }
      return $this;
   }


   /**
    * Gibt das Signal, zu dem diese Position gehört, zurück.
    *
    * @return Signal
    */
   public function getSignal() {
      if ($this->signal === null)
         $this->signal = Signal::dao()->getById($this->signal_id);
      return $this->signal;
   }


   /**
    * Fügt diese Instanz in die Datenbank ein.
    *
    * @return self
    */
   protected function insert() {
      $db = self::db();

      $created = $this->created;
      $version = $this->version;

      $ticket      =         $this->ticket;
      $type        =         $this->type;
      $lots        =         $this->lots;
      $symbol      =         $this->symbol;
      $opentime    =         $this->openTime;
      $openprice   =         $this->openPrice;
      $stoploss    =        !$this->stopLoss    ? 'null' : $this->stopLoss;
      $takeprofit  =        !$this->takeProfit  ? 'null' : $this->takeProfit;
      $commission  = is_null($this->commission) ? 'null' : $this->commission;
      $swap        = is_null($this->swap      ) ? 'null' : $this->swap;
      $magicnumber =        !$this->magicNumber ? 'null' : $this->magicNumber;
      $comment     = $db->escapeLiteral($this->comment);
      $signal_id   =         $this->signal_id;

      $db->begin();
      try {
         // OpenPosition einfügen
         $sql = "insert into t_openposition (version, created, ticket, type, lots, symbol, opentime, openprice, stoploss, takeprofit, commission, swap, magicnumber, comment, signal_id) values
                    ('$version', '$created', $ticket, '$type', $lots, '$symbol', '$opentime', $openprice, $stoploss, $takeprofit, $commission, $swap, $magicnumber, $comment, $signal_id)";
         $this->id = $db->execute($sql)
                        ->commit()
                        ->lastInsertId();
      }
      catch (\Exception $ex) {
         $db->rollback();
         throw $ex;
      }
      return $this;
   }


   /**
    * Aktualisiert diese Instanz in der Datenbank.
    *
    * @return self
    */
   protected function update() {
      $db = self::db();

      $id          = $this->id;
      $oldVersion  = $this->version;
      $newVersion  = $this->touch();

      $ticket      =         $this->ticket;
      $type        =         $this->type;
      $lots        =         $this->lots;
      $symbol      =         $this->symbol;
      $opentime    =         $this->openTime;
      $openprice   =         $this->openPrice;
      $stoploss    = is_null($this->stopLoss   ) ? 'null' : $this->stopLoss;
      $takeprofit  = is_null($this->takeProfit ) ? 'null' : $this->takeProfit;
      $commission  = is_null($this->commission ) ? 'null' : $this->commission;
      $swap        = is_null($this->swap       ) ? 'null' : $this->swap;
      $magicnumber = is_null($this->magicNumber) ? 'null' : $this->magicNumber;
      $comment     = $db->escapeLiteral($this->comment);
      $signal_id   = $this->signal_id;

      $db->begin();
      try {
         // OpenPosition updaten
         $sql = "update t_openposition
                    set ticket      =  $ticket,
                        type        = '$type',
                        lots        =  $lots,
                        symbol      = '$symbol',
                        opentime    = '$opentime',
                        openprice   =  $openprice,
                        stoploss    =  $stoploss,
                        takeprofit  =  $takeprofit,
                        commission  =  $commission,
                        swap        =  $swap,
                        magicnumber =  $magicnumber,
                        comment     =  $comment,
                        version     = '$newVersion'
                    where id = $id
                      and version = '$oldVersion'";

         if ($db->execute($sql)->lastAffectedRows() != 1) {
            $sql = "select version
                       from t_openposition
                       where id = $id";
            $found = $db->query($sql)->fetchField();
            $this->version = $oldVersion;
            throw new ConcurrentModificationException('Error updating '.__CLASS__.' (ticket='.$this->ticket.'), expected version: '.$oldVersion.', found version: '.$found);
         }

         // alles speichern
         $db->commit();
         $this->modifications = null;
      }
      catch (\Exception $ex) {
         $db->rollback();
         throw $ex;
      }
      return $this;
   }


   /**
    * Löscht diese Instanz aus der Datenbank.
    *
    * @return NULL
    */
   public function delete() {
      if (!$this->isPersistent()) throw new InvalidArgumentException('Cannot delete non-persistent '.__CLASS__);

      $db = self::db();
      $db->begin();
      try {
         $id  = $this->id;
         $sql = "delete from t_openposition
                    where id = $id";
         $db->execute($sql)
            ->commit();
      }
      catch (\Exception $ex) {
         $db->rollback();
         throw $ex;
      }

      $this->id = null;
      return null;
   }
}
