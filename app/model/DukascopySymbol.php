<?php
namespace rosasurfer\rost\model;


/**
 * Represents a Dukascopy symbol.
 *
 * @method string     getName()       Return the symbol name, i.e. the actual symbol.
 * @method int        getDigits()     Return the number of fractional digits of symbol prices.
 * @method RosaSymbol getRosaSymbol() Return the Rosatrader symbol this Dukascopy symbol is mapped to.
 */
class DukascopySymbol extends RosatraderModel {


    /** @var string - symbol name */
    protected $name;

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

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;


    /**
     * Return the start time of the symbol's available tick history (FXT).
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
     * Return the end time of the symbol's available tick history (FXT).
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
     * Return the start time of the symbol's available M1 history (FXT).
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
     * Return the end time of the symbol's available M1 history (FXT).
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
     * Refresh the start times of the available tick and M1 history.
     *
     * @return bool - whether or not at least one of the start times have changed
     */
    public function refreshHistoryStart() {
        return true;
    }
}
