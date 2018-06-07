<?php
namespace rosasurfer\xtrade\model;

use rosasurfer\db\orm\PersistableObject;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\util\Number;
use rosasurfer\xtrade\XTrade;


/**
 * OpenPosition
 *
 * @method int    getId()          Return the open position's id (primary key).
 * @method int    getTicket()      Return the open position's ticket number.
 * @method string getType()        Return the open position's ticket type.
 * @method float  getLots()        Return the open position's lot size.
 * @method string getSymbol()      Return the open position's symbol.
 * @method float  getOpenPrice()   Return the open position's open price.
 * @method float  getStopLoss()    Return the open position's stop loss price.
 * @method float  getTakeProfit()  Return the open position's take profit price.
 * @method int    getMagicNumber() Return the open position's magic number (if any).
 * @method string getComment()     Return the open position's order comment.
 * @method Signal getSignal()      Return the Signal the open position belongs to.
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

    /** @var Signal [transient] */
    protected $signal;


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

        $position          = new self();
        $position->created = date('Y-m-d H:i:s');
        $position->version = $position->created;

        $position->signal      = $signal;
        $position->ticket      =                 $data['ticket'     ];
        $position->type        =                 $data['type'       ];
        $position->lots        =                 $data['lots'       ];
        $position->symbol      =                 $data['symbol'     ];
        $position->openTime    = XTrade::fxtDate($data['opentime'   ]);
        $position->openPrice   =                 $data['openprice'  ];
        $position->stopLoss    =           isSet($data['stoploss'   ]) ? $data['stoploss'   ] : null;
        $position->takeProfit  =           isSet($data['takeprofit' ]) ? $data['takeprofit' ] : null;
        $position->commission  =           isSet($data['commission' ]) ? $data['commission' ] : null;
        $position->swap        =           isSet($data['swap'       ]) ? $data['swap'       ] : null;
        $position->magicNumber =           isSet($data['magicnumber']) ? $data['magicnumber'] : null;
        $position->comment     =           isSet($data['comment'    ]) ? $data['comment'    ] : null;

        return $position;
    }


    /**
     * Return the creation time of the instance.
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - formatted creation time
     */
    public function getCreated($format = 'Y-m-d H:i:s')  {
        if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
        if ($format == 'Y-m-d H:i:s')
            return $this->created;
        return date($format, strToTime($this->created));
    }


    /**
     * Return the version of the instance (last modification time).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - formatted last modification time
     */
    public function getVersion($format = 'Y-m-d H:i:s')  {
        if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
        if ($format == 'Y-m-d H:i:s')
            return $this->version;
        return date($format, strToTime($this->version));
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
     * @param  string $format [optional] - Zeitformat (default: 'Y-m-d H:i:s')
     *
     * @return string - Zeitpunkt
     */
    public function getOpenTime($format='Y-m-d H:i:s') {
        if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
        if ($format == 'Y-m-d H:i:s')
            return $this->created;
        return date($format, strToTime($this->created));
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
        if (isSet($value) && !is_int($value) && !is_float($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
        if ($value < 0)                                            throw new InvalidArgumentException('Invalid StopLoss value '.$value);

        $value = $value ? (float) $value : null;

        if ($value !== $this->stopLoss) {
            $this->stopLoss = $value;

            $this->isPersistent() && $this->modified();
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
        if (isSet($value) && !is_int($value) && !is_float($value)) throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
        if ($value < 0)                                            throw new InvalidArgumentException('Invalid TakeProfit value '.$value);

        $value = $value ? (float) $value : null;

        if ($value !== $this->takeProfit) {
            $this->takeProfit = $value;

            $this->isPersistent() && $this->modified();
        }
        return $this;
    }


    /**
     * Update the version field as this is not yet automated by the ORM.
     *
     * {@inheritdoc}
     */
    protected function beforeUpdate() {
        $this->version = date('Y-m-d H:i:s');
        return true;
    }
}
