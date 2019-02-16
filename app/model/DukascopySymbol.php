<?php
namespace rosasurfer\rt\model;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\dukascopy\Dukascopy;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_M1;


/**
 * DukascopySymbol
 *
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
    protected $historyTicksStart;

    /** @var string - end time of the available tick history (FXT) */
    protected $historyTicksEnd;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyM1Start;

    /** @var string - end time of the available M1 history (FXT) */
    protected $historyM1End;

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;


    /**
     * Return the start time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyTicksStart) || $format=='Y-m-d H:i:s')
            return $this->historyTicksStart;
        return gmdate($format, strtotime($this->historyTicksStart.' GMT'));
    }


    /**
     * Return the end time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryTicksEnd($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyTicksEnd) || $format=='Y-m-d H:i:s')
            return $this->historyTicksEnd;
        return gmdate($format, strtotime($this->historyTicksEnd.' GMT'));
    }


    /**
     * Return the start time of the symbol's available M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyM1Start) || $format=='Y-m-d H:i:s')
            return $this->historyM1Start;
        return gmdate($format, strtotime($this->historyM1Start.' GMT'));
    }


    /**
     * Return the end time of the symbol's available M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryM1End($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyM1End) || $format=='Y-m-d H:i:s')
            return $this->historyM1End;
        return gmdate($format, strtotime($this->historyM1End.' GMT'));
    }


    /**
     * Show history status information.
     *
     * @param  bool $local [optional] - whether to show local or remotely fetched history status
     *                                  (default: local)
     * @return bool - success status
     */
    public function showHistoryStatus($local = true) {
        if (!is_bool($local)) throw new IllegalTypeException('Illegal type of parameter $local: '.gettype($local));

        if ($local) {
            $start = $this->getHistoryM1Start('D, d-M-Y H:i');
            $end   = $this->getHistoryM1End('D, d-M-Y H:i');

            if (!$start && !$end) {
                echoPre('[Info]    '.$this->name.'  available history unknown');
            }
            else {
                $start && echoPre('[Info]    '.$this->name.'  history starts '.$start.' FXT');
                $end   && echoPre('[Info]    '.$this->name.'  history ends   '.$end.' FXT');
            }
        }
        else {
            echoPre('[Info]    '.$this->name.'  fetching remote history status');

            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di()['dukascopy'];
            $historyStart = $dukascopy->fetchHistoryStart($this->name);
            echoPre('$historyStart: '.$historyStart);
        }
        return true;
    }


    /**
     * Refresh the start times of the available tick and M1 history.
     *
     * @return bool - whether at least one of the starttimes have changed
     */
    public function refreshHistoryStart() {
        return true;
    }


    /**
     * Get the history for the specified period and time.
     *
     * @param  int $period - timeframe identifier:
     *                       PERIOD_TICKS:           returns the history for one hour
     *                       PERIOD_M1 to PERIOD_D1: returns the history for one day
     *                       PERIOD_W1:              returns the history for one week
     *                       PERIOD_MN1:             returns the history for one month
     *                       PERIOD_Q1:              returns the history for one quarter (3 months)
     * @param  int $time   - FXT timestamp, if 0 (zero) the oldest available history for the period is returned
     *
     * @return array[] - If the specified history is not available an empty array is returned. Otherwise a timeseries array
     *                   is returned with each element describing a single bar as follows:
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public function getHistory($period, $time) {
        if (!is_int($period))     throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if (!is_int($time))       throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');

        echoPre('[Info]    '.$this->name.'  getting M1 history'.($time ? ' for '.gmdate('D, d-M-Y', $time) : ' since start'));

        // determine needed files
        // load remote files

        $time       -= $time%DAY;
        $currentDay  = $time;
        $previousDay = $time - 1*DAY;

        /*
        loadHistory($symbol, $day, 'bid');      // Bid-Daten laden
        loadHistory($symbol, $day, 'ask');      // Ask-Daten laden
        mergeHistory($symbol, $day);            // Bid und Ask mergen
        saveBars($symbol, $day);                // gemergte Daten speichern
        */

        return [];
    }
}
