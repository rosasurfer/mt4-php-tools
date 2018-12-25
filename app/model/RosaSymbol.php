<?php
namespace rosasurfer\rost\model;


/**
 * Represents a Rosatrader symbol.
 *
 * @method string          getType()            Return the instrument type (forex|metals|synthetic).
 * @method string          getName()            Return the symbol name, i.e. the actual symbol.
 * @method string          getDescription()     Return the symbol description.
 * @method int             getDigits()          Return the number of fractional digits of symbol prices.
 * @method DukascopySymbol getDukascopySymbol() Return the Dukascopy symbol mapped to this RosaTrader symbol.
 */
class RosaSymbol extends RosatraderModel {


    /** @var string - instrument type (forex|metals|synthetic) */
    protected $type;

    /** @var string - symbol name */
    protected $name;

    /** @var string - symbol description */
    protected $description;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var string - starttime of the available tick history (FXT) */
    protected $tickHistoryFrom;

    /** @var string - endtime of the available tick history (FXT) */
    protected $tickHistoryTo;

    /** @var string - starttime of the available M1 history (FXT) */
    protected $m1HistoryFrom;

    /** @var string - endtime of the available M1 history (FXT) */
    protected $m1HistoryTo;

    /** @var string - starttime of the available D1 history (FXT) */
    protected $d1HistoryFrom;

    /** @var string - endtime of the available D1 history (FXT) */
    protected $d1HistoryTo;

    /** @var DukascopySymbol [transient] - the Dukascopy symbol mapped to this RosaTrader symbol */
    protected $dukascopySymbol;


    /**
     * Return the start time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time
     */
    public function getTickHistoryFrom($format = 'Y-m-d H:i:s') {
        if (!isSet($this->tickHistoryFrom) || $format=='Y-m-d H:i:s')
            return $this->tickHistoryFrom;
        return gmDate($format, strToTime($this->tickHistoryFrom));
    }


    /**
     * Return the end time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - end time
     */
    public function getTickHistoryTo($format = 'Y-m-d H:i:s') {
        if (!isSet($this->tickHistoryTo) || $format=='Y-m-d H:i:s')
            return $this->tickHistoryTo;
        return gmDate($format, strToTime($this->tickHistoryTo));
    }


    /**
     * Return the start time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time
     */
    public function getM1HistoryFrom($format = 'Y-m-d H:i:s') {
        if (!isSet($this->m1HistoryFrom) || $format=='Y-m-d H:i:s')
            return $this->m1HistoryFrom;
        return gmDate($format, strToTime($this->m1HistoryFrom));
    }


    /**
     * Return the end time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - end time
     */
    public function getM1HistoryTo($format = 'Y-m-d H:i:s') {
        if (!isSet($this->m1HistoryTo) || $format=='Y-m-d H:i:s')
            return $this->m1HistoryTo;
        return gmDate($format, strToTime($this->m1HistoryTo));
    }


    /**
     * Return the start time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time
     */
    public function getD1HistoryFrom($format = 'Y-m-d H:i:s') {
        if (!isSet($this->d1HistoryFrom) || $format=='Y-m-d H:i:s')
            return $this->d1HistoryFrom;
        return gmDate($format, strToTime($this->d1HistoryFrom));
    }


    /**
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - end time
     */
    public function getD1HistoryTo($format = 'Y-m-d H:i:s') {
        if (!isSet($this->d1HistoryTo) || $format=='Y-m-d H:i:s')
            return $this->d1HistoryTo;
        return gmDate($format, strToTime($this->d1HistoryTo));
    }
}
