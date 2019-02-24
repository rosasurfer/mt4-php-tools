<?php
namespace rosasurfer\rt\model;

use rosasurfer\console\Output;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\FXT;
use rosasurfer\rt\lib\dukascopy\Dukascopy;

use function rosasurfer\rt\periodDescription;
use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_TICKS;
use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_D1;


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
    protected $historyStartTicks;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyStartM1;

    /** @var string - start time of the available H1 history (FXT) */
    protected $historyStartH1;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyStartD1;

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;


    /**
     * Return the start time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTicks($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartTicks) || $format=='Y-m-d H:i:s')
            return $this->historyStartTicks;
        return gmdate($format, strtotime($this->historyStartTicks.' GMT'));
    }


    /**
     * Return the start time of the symbol's available M1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartM1) || $format=='Y-m-d H:i:s')
            return $this->historyStartM1;
        return gmdate($format, strtotime($this->historyStartM1.' GMT'));
    }


    /**
     * Return the start time of the symbol's available H1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartH1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartH1) || $format=='Y-m-d H:i:s')
            return $this->historyStartH1;
        return gmdate($format, strtotime($this->historyStartH1.' GMT'));
    }


    /**
     * Return the start time of the symbol's available D1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartD1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartD1) || $format=='Y-m-d H:i:s')
            return $this->historyStartD1;
        return gmdate($format, strtotime($this->historyStartD1.' GMT'));
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
        /** @var Output $output */
        $output = $this->di(Output::class);

        if ($local) {
            $startTicks = $this->getHistoryStartTicks('D, d-M-Y H:i:s \F\X\T');
            $startM1    = $this->getHistoryStartM1   ('D, d-M-Y H:i:s \F\X\T');
            $startH1    = $this->getHistoryStartH1   ('D, d-M-Y H:i:s \F\X\T');
            $startD1    = $this->getHistoryStartD1   ('D, d-M-Y H:i:s \F\X\T');

            if (!$startTicks && !$startM1 && !$startH1 && !$startD1) {
                $output->out('[Info]    '.$this->name.'  local Dukascopy status not available');
            }
            else {
                $startTicks && $output->out('[Info]    '.$this->name.'  Dukascopy TICK history starts '.$startTicks);
                $startM1    && $output->out('[Info]    '.$this->name.'  Dukascopy M1   history starts '.$startM1   );
                $startH1    && $output->out('[Info]    '.$this->name.'  Dukascopy H1   history starts '.$startH1   );
                $startD1    && $output->out('[Info]    '.$this->name.'  Dukascopy D1   history starts '.$startD1   );
            }
        }
        else {
            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $historyStart = $dukascopy->fetchHistoryStart($this->name);

            foreach ($historyStart as $timeframe => $time) {
                $period     = periodDescription($timeframe);
                $datetime   = \DateTime::createFromFormat(is_int($time) ? 'U':'U.u', is_int($time) ? (string)$time : number_format($time, 6, '.', ''));
                $formatted  = $datetime->format('D, d-M-Y H:i'.(is_int($time) ? '':':s.u'));
                is_float($time) && $formatted = strLeft($formatted, -3);
                $formatted .= ' FXT';
                $output->out('[Info]    '.$this->name.'  Dukascopy '.str_pad($period, 4).' history starts '.$formatted);
            }
        }
        return true;
    }


    /**
     * Update history start times.
     *
     * @param  array $times - array with start times per timeframe
     *
     * @return bool - whether at least one of the start times have changed
     */
    public function updateHistoryStart(array $times) {
        /** @var Output $output */
        $output = $this->di(Output::class);

        $localTime = $this->historyStartTicks;
        $remoteTime = isset($times[PERIOD_TICKS]) ? FXT::fxDate('Y-m-d H:i:s', (int)$times[PERIOD_TICKS]) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartTicks = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  TICK history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartM1;
        $remoteTime = isset($times[PERIOD_M1]) ? FXT::fxDate('Y-m-d H:i:s', (int)$times[PERIOD_M1]) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartM1 = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  M1   history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartH1;
        $remoteTime = isset($times[PERIOD_H1]) ? FXT::fxDate('Y-m-d H:i:s', (int)$times[PERIOD_H1]) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartH1 = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  H1   history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartD1;
        $remoteTime = isset($times[PERIOD_D1]) ? FXT::fxDate('Y-m-d H:i:s', (int)$times[PERIOD_D1]) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartD1 = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  D1   history start changed: '.($remoteTime ?: 'n/a'));
        }
        return $this->isModified();
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
