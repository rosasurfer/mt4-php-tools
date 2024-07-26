<?php
namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\IHistorySource;
use rosasurfer\rt\lib\dukascopy\Dukascopy;

use function rosasurfer\rt\fxDate;
use function rosasurfer\rt\igmdate;
use function rosasurfer\rt\periodDescription;
use function rosasurfer\rt\periodToStr;
use function rosasurfer\rt\unixTime;

use const rosasurfer\rt\PERIOD_TICK;
use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_D1;


/**
 * DukascopySymbol
 *
 * Represents a Dukascopy symbol.
 *
 * @method        string                                  getName()       Return the symbol name, i.e. the actual symbol.
 * @method        int                                     getDigits()     Return the number of fractional digits of symbol prices.
 * @method        \rosasurfer\rt\model\RosaSymbol         getRosaSymbol() Return the Rosatrader symbol this Dukascopy symbol is mapped to.
 * @method static \rosasurfer\rt\model\DukascopySymbolDAO dao()           Return the {@link DukascopySymbolDAO} for the calling class.
 */
class DukascopySymbol extends RosatraderModel implements IHistorySource {


    /** @var string - symbol name */
    protected $name;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var string - start time of the available tick history (FXT) */
    protected $historyStartTick;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyStartM1;

    /** @var string - start time of the available H1 history (FXT) */
    protected $historyStartH1;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyStartD1;

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;


    /**
     * Return the instrument's quote resolution (the value of 1 point).
     *
     * @return double
     */
    public function getPointValue() {
        return 1/pow(10, $this->digits);
    }


    /**
     * Return the start time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartTick) || $format=='Y-m-d H:i:s')
            return $this->historyStartTick;
        return gmdate($format, strtotime($this->historyStartTick.' GMT'));
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
        Assert::bool($local);

        if ($local) {
            $startTick = $this->getHistoryStartTick('D, d-M-Y H:i:s \F\X\T');
            $startM1   = $this->getHistoryStartM1  ('D, d-M-Y H:i:s \F\X\T');
            $startH1   = $this->getHistoryStartH1  ('D, d-M-Y H:i:s \F\X\T');
            $startD1   = $this->getHistoryStartD1  ('D, d-M-Y H:i:s \F\X\T');

            if (!$startTick && !$startM1 && !$startH1 && !$startD1) {
                Output::out('[Info]    '.$this->name.'  local Dukascopy status not available');
            }
            else {
                $startTick && Output::out('[Info]    '.$this->name.'  Dukascopy TICK history starts '.$startTick);
                $startM1   && Output::out('[Info]    '.$this->name.'  Dukascopy M1   history starts '.$startM1  );
                $startH1   && Output::out('[Info]    '.$this->name.'  Dukascopy H1   history starts '.$startH1  );
                $startD1   && Output::out('[Info]    '.$this->name.'  Dukascopy D1   history starts '.$startD1  );
            }
        }
        else {
            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $historyStart = $dukascopy->fetchHistoryStart($this);

            foreach ($historyStart as $timeframe => $time) {
                $period     = periodDescription($timeframe);
                $datetime   = \DateTime::createFromFormat(is_int($time) ? 'U':'U.u', is_int($time) ? (string)$time : number_format($time, 6, '.', ''));
                $formatted  = $datetime->format('D, d-M-Y H:i'.(is_int($time) ? '':':s.u'));
                is_float($time) && $formatted = strLeft($formatted, -3);
                $formatted .= ' FXT';
                Output::out('[Info]    '.$this->name.'  Dukascopy '.str_pad($period, 4).' history starts '.$formatted);
            }
        }
        return true;
    }


    /**
     * Update locally stored history start times with the passed data.
     *
     * @param  array $times - array with start times per timeframe
     *
     * @return bool - whether at least one of the start times have changed
     */
    public function updateHistoryStart(array $times) {
        $localTime = $this->historyStartTick;
        $remoteTime = isset($times[PERIOD_TICK]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_TICK], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartTick = $remoteTime;
            $this->modified();
            Output::out('[Info]    '.$this->getName().'  TICK history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartM1;
        $remoteTime = isset($times[PERIOD_M1]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_M1], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartM1 = $remoteTime;
            $this->modified();
            Output::out('[Info]    '.$this->getName().'  M1   history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartH1;
        $remoteTime = isset($times[PERIOD_H1]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_H1], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartH1 = $remoteTime;
            $this->modified();
            Output::out('[Info]    '.$this->getName().'  H1   history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartD1;
        $remoteTime = isset($times[PERIOD_D1]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_D1], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartD1 = $remoteTime;
            $this->modified();
            Output::out('[Info]    '.$this->getName().'  D1   history start changed: '.($remoteTime ?: 'n/a'));
        }
        return $this->isModified();
    }


    /**
     *
     */
    public function getHistory($period, $time, $optimized = false) {
        Assert::int($period, '$period');
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        Assert::int($time, '$time');

        if (!$time) {
            if (!$time = (int) $this->getHistoryStartM1('U')) {
                Output::error('[Error]   '.str_pad($this->name, 6).'  history start for M1 not available');
                return [];
            }
            if (igmdate('d', $time) == igmdate('d', unixTime($time)))       // if history starts at or after 00:00 GMT skip
                $time += 1*DAY - $time%DAY ;                                // the partial FXT day: it would be incomplete
        }
        /** @var Dukascopy $dukascopy */
        $dukascopy = $this->di(Dukascopy::class);
        return $dukascopy->getHistory($this, $period, $time, $optimized);
    }
}
