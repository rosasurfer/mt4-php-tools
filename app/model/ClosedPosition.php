<?php
namespace rosasurfer\xtrade\model;

use rosasurfer\db\orm\PersistableObject;

use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\util\Date;
use rosasurfer\util\Number;

use rosasurfer\xtrade\Tools;


/**
 * ClosedPosition
 */
class ClosedPosition extends PersistableObject {


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

    /** @var string */
    protected $closeTime;

    /** @var float */
    protected $closePrice;

    /** @var float */
    protected $stopLoss;

    /** @var float */
    protected $takeProfit;

    /** @var float */
    protected $commission;

    /** @var float */
    protected $swap;

    /** @var float */
    protected $grossProfit;

    /** @var float */
    protected $netProfit;

    /** @var int */
    protected $magicNumber;

    /** @var string */
    protected $comment;

    /** @var int */
    protected $signal_id;

    /** @var Signal */
    private $signal;


    // Getter
    public function getId()          { return $this->id;          }
    public function getTicket()      { return $this->ticket;      }
    public function getType()        { return $this->type;        }
    public function getLots()        { return $this->lots;        }
    public function getSymbol()      { return $this->symbol;      }
    public function getOpenPrice()   { return $this->openPrice;   }
    public function getClosePrice()  { return $this->closePrice;  }
    public function getStopLoss()    { return $this->stopLoss;    }
    public function getTakeProfit()  { return $this->takeProfit;  }
    public function getMagicNumber() { return $this->magicNumber; }
    public function getComment()     { return $this->comment;     }
    public function getSignal_id()   { return $this->signal_id;   }


    /**
     * Ueberladene Methode.  Erzeugt eine neue geschlossene Position.
     *
     * @return static
     */
    public static function create() {
        if (func_num_args() != 2) throw new RuntimeException('Invalid number of function arguments: '.func_num_args());
        $arg1 = func_get_arg(0);
        $arg2 = func_get_arg(1);

        if ($arg1 instanceof OpenPosition)
            return self::create_1($arg1, $arg2);      // (OpenPosition $position, array $data)
        return self::create_2($arg1, $arg2);         // (Signal $signal, array $data)
    }


    /**
     * Erzeugt eine neue geschlossene Position anhand einer vormals offenen Position.
     *
     * @param  OpenPosition $openPosition - vormals offene Position
     * @param  array        $data         - Positionsdaten
     *
     * @return static
     */
    private static function create_1(OpenPosition $openPosition, array $data) {
        $position = new static();

        $position->ticket      =                $data['ticket'     ];
        $position->type        =                $data['type'       ];
        $position->lots        =                $data['lots'       ];
        $position->symbol      =                $data['symbol'     ];
        $position->openTime    = Tools::fxtDate($data['opentime'   ]);
        $position->openPrice   =                $data['openprice'  ];
        $position->closeTime   = Tools::fxtDate($data['closetime'  ]);
        $position->closePrice  =                $data['closeprice' ];
        $position->stopLoss    =          isSet($data['stoploss'   ]) ? $data['stoploss'   ] : $openPosition->getStopLoss();
        $position->takeProfit  =          isSet($data['takeprofit' ]) ? $data['takeprofit' ] : $openPosition->getTakeProfit();
        $position->commission  =          isSet($data['commission' ]) ? $data['commission' ] : $openPosition->getCommission();
        $position->swap        =          isSet($data['swap'       ]) ? $data['swap'       ] : $openPosition->getSwap();
        $position->grossProfit =          isSet($data['grossprofit']) ? $data['grossprofit'] : null;
        $position->netProfit   =                $data['netprofit'  ];
        $position->magicNumber =          isSet($data['magicnumber']) ? $data['magicnumber'] : $openPosition->getMagicNumber();
        $position->comment     =          isSet($data['comment'    ]) ? $data['comment'    ] : $openPosition->getComment();

        $position->signal_id = $openPosition->getSignal_id();

        return $position;
    }


    /**
     * Erzeugt eine neue geschlossene Position anhand der angegebenen Rohdaten.
     *
     * @param  Signal $signal - Signal der Position
     * @param  array  $data   - Positionsdaten
     *
     * @return static
     */
    private static function create_2(Signal $signal, array $data) {
        if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process '.__CLASS__.' for non-persistent '.get_class($signal));

        $position = new static();

        $position->ticket      =                $data['ticket'     ];
        $position->type        =                $data['type'       ];
        $position->lots        =                $data['lots'       ];
        $position->symbol      =                $data['symbol'     ];
        $position->openTime    = Tools::fxtDate($data['opentime'   ]);
        $position->openPrice   =                $data['openprice'  ];
        $position->closeTime   = Tools::fxtDate($data['closetime'  ]);
        $position->closePrice  =                $data['closeprice' ];
        $position->stopLoss    =          isSet($data['stoploss'   ]) ? $data['stoploss'   ] : null;
        $position->takeProfit  =          isSet($data['takeprofit' ]) ? $data['takeprofit' ] : null;
        $position->commission  =          isSet($data['commission' ]) ? $data['commission' ] : null;
        $position->swap        =          isSet($data['swap'       ]) ? $data['swap'       ] : null;
        $position->grossProfit =          isSet($data['grossprofit']) ? $data['grossprofit'] : null;
        $position->netProfit   =                $data['netprofit'  ];
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
    public function getOpenTime($format='Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->openTime;
        return Date::format($this->openTime, $format);
    }


    /**
     * Gibt die CloseTime dieser Position zurueck.
     *
     * @param  string $format - Zeitformat (default: 'Y-m-d H:i:s')
     *
     * @return string - Zeitpunkt
     */
    public function getCloseTime($format='Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->closeTime;
        return Date::format($this->closeTime, $format);
    }


    /**
     * Gibt den Commission-Betrag dieser Position zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return float|string|null - Betrag oder NULL, wenn der Betrag nicht verfuegbar ist
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
     * @return float|string|null - Betrag oder NULL, wenn der Betrag nicht verfuegbar ist
     */
    public function getSwap($decimals=2, $separator='.') {
        if (is_null($this->swap) || !func_num_args())
            return $this->swap;
        return Number::formatMoney($this->swap, $decimals, $separator);
    }


    /**
     * Gibt den Gross-Profit-Betrag dieser Position zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return float|string|null - Betrag oder NULL, wenn der Betrag nicht verfuegbar ist
     */
    public function getGrossProfit($decimals=2, $separator='.') {
        if (is_null($this->grossProfit) || !func_num_args())
            return $this->grossProfit;
        return Number::formatMoney($this->grossProfit, $decimals, $separator);
    }


    /**
     * Gibt den Net-Profit-Betrag dieser Position zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return float|string - Betrag
     */
    public function getNetProfit($decimals=2, $separator='.') {
        if (!func_num_args())
            return $this->netProfit;
        return Number::formatMoney($this->netProfit, $decimals, $separator);
    }


    /**
     * Gibt das Signal, zu dem diese Position gehoert, zurueck.
     *
     * @return Signal
     */
    public function getSignal() {
        if ($this->signal === null) {
            /** @var SignalDAO $signalDao */
            $signalDao = Signal::dao();
            $this->signal = $signalDao->getById($this->signal_id);
        }
        return $this->signal;
    }
}
