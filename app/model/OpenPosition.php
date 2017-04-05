<?php
namespace rosasurfer\trade\model;

use rosasurfer\db\orm\PersistableObject;

use rosasurfer\exception\ConcurrentModificationException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use rosasurfer\trade\myfx\MyFX;

use rosasurfer\util\Date;
use rosasurfer\util\Number;


/**
 * OpenPosition
 */
class OpenPosition extends PersistableObject {


    /** @var int - primary key */
    protected $id;

    /** @var string - time of creation */
    protected $created;

    /** @var string - time of last modification */
    protected $version;

    /** @var int */
    protected $ticket;

    /** @var string */
    protected $type;

    /** @var float */
    protected $lots;

    /** @var string */
    protected $symbol;

    /** @var string */
    protected $openTime;

    /** @var float */
    protected $openPrice;

    /** @var float */
    protected $stopLoss;

    /** @var float */
    protected $takeProfit;

    /** @var float */
    protected $commission;

    /** @var float */
    protected $swap;

    /** @var int */
    protected $magicNumber;

    /** @var string */
    protected $comment;

    /** @var int */
    protected $signal_id;

    /** @var Signal */
    protected $signal;


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
     * @param  Signal $signal - Signal, zu dem die Position gehoert
     * @param  array  $data   - Positionsdaten
     *
     * @return static
     */
    public static function create(Signal $signal, array $data) {
        if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process '.__CLASS__.' for non-persistent '.get_class($signal));

        $position = new static();

        $position->ticket      =               $data['ticket'     ];
        $position->type        =               $data['type'       ];
        $position->lots        =               $data['lots'       ];
        $position->symbol      =               $data['symbol'     ];
        $position->openTime    = MyFX::fxtDate($data['opentime'   ]);
        $position->openPrice   =               $data['openprice'  ];
        $position->stopLoss    =         isSet($data['stoploss'   ]) ? $data['stoploss'   ] : null;
        $position->takeProfit  =         isSet($data['takeprofit' ]) ? $data['takeprofit' ] : null;
        $position->commission  =         isSet($data['commission' ]) ? $data['commission' ] : null;
        $position->swap        =         isSet($data['swap'       ]) ? $data['swap'       ] : null;
        $position->magicNumber =         isSet($data['magicnumber']) ? $data['magicnumber'] : null;
        $position->comment     =         isSet($data['comment'    ]) ? $data['comment'    ] : null;
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
     * Return the version string of the instance.
     *
     * @param  string $format - format as used by date($format, $timestamp)
     *
     * @return string - version (last modification time)
     */
    public function getVersion($format = 'Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->version;
        return Date::format($this->version, $format);
    }


    /**
     * Gibt die Beschreibung des OperationTypes dieser Position zurueck.
     *
     * @return string - Beschreibung
     */
    public function getTypeDescription() {
        return ucFirst($this->type);
    }


    /**
     * Gibt die OpenTime dieser Position zurueck.
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
     * Gibt den Commission-Betrag dieser Position zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return string|float|null - Betrag oder NULL, wenn der Betrag nicht verfuegbar ist
     */
    public function getCommission($decimals=2, $separator='.') {
        if (is_null($this->commission) || !func_num_args())
            return $this->commission;
        return Number::formatMoney($this->commission, $decimals, $separator);
    }


    /**
     * Gibt den Swap-Betrag dieser Position zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return string|float|null - Betrag oder NULL, wenn der Betrag nicht verfuegbar ist
     */
    public function getSwap($decimals=2, $separator='.') {
        if (is_null($this->swap) || !func_num_args())
            return $this->swap;
        return Number::formatMoney($this->swap, $decimals, $separator);
    }


    /**
     * Setzt den StopLoss dieser Position auf den angegebenen Wert.
     *
     * @param  float $value - StopLoss-Value (0 oder NULL loeschen den aktuellen Wert)
     *
     * @return $this
     */
    public function setStopLoss($value) {
        if (!is_null($value) && !is_int($value) && !is_float($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
        if ($value < 0)                                               throw new InvalidArgumentException('Invalid StopLoss value '.$value);

        if (!$value)
            $value = null;

        if ($value !== $this->stopLoss) {
            $this->stopLoss = $value;

            $this->isPersistent() && $this->_modified=true;
        }
        return $this;
    }


    /**
     * Setzt den TakeProfit dieser Position auf den angegebenen Wert.
     *
     * @param  float $value - TakeProfit-Value (0 oder NULL loeschen den aktuellen Wert)
     *
     * @return $this
     */
    public function setTakeProfit($value) {
        if (!is_null($value) && !is_int($value) && !is_float($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
        if ($value < 0)                                               throw new InvalidArgumentException('Invalid TakeProfit value '.$value);

        if (!$value)
            $value = null;

        if ($value !== $this->takeProfit) {
            $this->takeProfit = $value;

            $this->isPersistent() && $this->_modified=true;
        }
        return $this;
    }


    /**
     * Gibt das Signal, zu dem diese Position gehoert, zurueck.
     *
     * @return Signal
     */
    public function getSignal() {
        if ($this->signal === null)
            $this->signal = Signal::dao()->getById($this->signal_id);
        return $this->signal;
    }


    /**
     * Aktualisiert diese Instanz in der Datenbank.
     *
     * @return $this
     */
    protected function update() {
        $dao = self::dao();

        $id          =                     $this->id;
        $version     =                     $this->version;
        $oldVersion  = $dao->escapeLiteral($this->version);
        $newVersion  = $dao->escapeLiteral($this->touch());

        $ticket      =                     $this->ticket;
        $type        = $dao->escapeLiteral($this->type);
        $lots        =                     $this->lots;
        $symbol      = $dao->escapeLiteral($this->symbol);
        $opentime    = $dao->escapeLiteral($this->openTime);
        $openprice   =                     $this->openPrice;
        $stoploss    = $dao->escapeLiteral($this->stopLoss);
        $takeprofit  = $dao->escapeLiteral($this->takeProfit);
        $commission  = $dao->escapeLiteral($this->commission);
        $swap        = $dao->escapeLiteral($this->swap);
        $magicnumber = $dao->escapeLiteral($this->magicNumber);
        $comment     = $dao->escapeLiteral($this->comment);
        $signal_id   =                     $this->signal_id;

        // OpenPosition updaten
        $sql = "update :OpenPosition
                   set ticket      = $ticket,
                       type        = $type,
                       lots        = $lots,
                       symbol      = $symbol,
                       opentime    = $opentime,
                       openprice   = $openprice,
                       stoploss    = $stoploss,
                       takeprofit  = $takeprofit,
                       commission  = $commission,
                       swap        = $swap,
                       magicnumber = $magicnumber,
                       comment     = $comment,
                       version     = $newVersion
                   where id      = $id
                     and version = $oldVersion";

        if ($dao->execute($sql)->db()->lastAffectedRows() != 1) {
            $this->version = $version;
            $found = $dao->refresh($this);
            throw new ConcurrentModificationException('Error updating '.__CLASS__.' (ticket='.$this->ticket.'), expected version: '.$oldVersion.', found version: '.$found->getOversion());
        }

        $this->_modifications = null;
        $this->_modified      = false;
        return $this;
    }


    /**
     * Loescht diese Instanz aus der Datenbank.
     *
     * @return NULL
     */
    public function delete() {
        if (!$this->isPersistent()) throw new InvalidArgumentException('Cannot delete non-persistent '.__CLASS__);

        $id  = $this->id;
        $sql = "delete from :OpenPosition
                      where id = $id";
        self::dao()->execute($sql);

        $this->id = null;
        return null;
    }
}
