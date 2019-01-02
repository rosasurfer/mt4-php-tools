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

    /** @var string - start time of the available tick history (FXT) */
    protected $historyStartTicks;

    /** @var string - end time of the available tick history (FXT) */
    protected $historyEndTicks;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyStartM1;

    /** @var string - end time of the available M1 history (FXT) */
    protected $historyEndM1;

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;


    /**
     * Return the start time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTicks($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyStartTicks) || $format=='Y-m-d H:i:s')
            return $this->historyStartTicks;
        return gmDate($format, strToTime($this->historyStartTicks.' GMT'));
    }


    /**
     * Return the end time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndTicks($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyEndTicks) || $format=='Y-m-d H:i:s')
            return $this->historyEndTicks;
        return gmDate($format, strToTime($this->historyEndTicks.' GMT'));
    }


    /**
     * Return the start time of the symbol's available M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyStartM1) || $format=='Y-m-d H:i:s')
            return $this->historyStartM1;
        return gmDate($format, strToTime($this->historyStartM1.' GMT'));
    }


    /**
     * Return the end time of the symbol's available M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndM1($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyEndM1) || $format=='Y-m-d H:i:s')
            return $this->historyEndM1;
        return gmDate($format, strToTime($this->historyEndM1.' GMT'));
    }


    /**
     * Refresh the start times of the available tick and M1 history.
     *
     * @return bool - whether at least one of the starttimes have changed
     */
    public function refreshHistoryStart() {
        return true;
    }
}
