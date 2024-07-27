<?php
namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\process\Process;

use rosasurfer\rt\lib\IHistorySource;
use rosasurfer\rt\lib\Rosatrader as RT;
use rosasurfer\rt\lib\synthetic\GenericSynthesizer;
use rosasurfer\rt\lib\synthetic\ISynthesizer;

use function rosasurfer\ministruts\strRight;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\isGoodFriday;
use function rosasurfer\rt\isHoliday;
use function rosasurfer\rt\isWeekend;

use function rosasurfer\ministruts\first;
use function rosasurfer\ministruts\last;
use function rosasurfer\ministruts\pluralize;
use function rosasurfer\ministruts\strLeftTo;
use function rosasurfer\ministruts\is_class;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\HOURS;
use const rosasurfer\ministruts\MINUTES;

use const rosasurfer\rt\PERIOD_M1;


/**
 * Represents a Rosatrader symbol.
 *
 * @method        string                               getType()            Return the instrument type (forex|metals|synthetic).
 * @method        int                                  getGroup()           Return the symbol's group id.
 * @method        string                               getName()            Return the symbol name, i.e. the actual symbol.
 * @method        string                               getDescription()     Return the symbol description.
 * @method        int                                  getDigits()          Return the number of fractional digits of symbol prices.
 * @method        int                                  getUpdateOrder()     Return the symbol's update order value.
 * @method        string                               getFormula()         Return a synthetic instrument's calculation formula (LaTeX).
 * @method        \rosasurfer\rt\model\DukascopySymbol getDukascopySymbol() Return the {@link DukascopySymbol} mapped to this Rosatrader symbol.
 * @method static \rosasurfer\rt\model\RosaSymbolDAO   dao()                Return the {@link RosaSymbolDAO} for the calling class.
 */
class RosaSymbol extends RosatraderModel {


    /** @var string */
    const TYPE_FOREX = 'forex';

    /** @var string */
    const TYPE_METAL = 'metals';

    /** @var string */
    const TYPE_SYNTHETIC = 'synthetic';


    /** @var string - instrument type (forex|metals|synthetic) */
    protected $type;

    /** @var int - grouping id for view separation */
    protected $group;

    /** @var string - symbol name */
    protected $name;

    /** @var string - symbol description */
    protected $description;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var int - on multi-symbol updates required symbols are updated before dependent ones */
    protected $updateOrder = 9999;

    /** @var string - LaTeX formula for calculation of synthetic instruments */
    protected $formula;

    /** @var string - start time of the available tick history (FXT) */
    protected $historyStartTick;

    /** @var string - end time of the available tick history (FXT) */
    protected $historyEndTick;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyStartM1;

    /** @var string - end time of the available M1 history (FXT) */
    protected $historyEndM1;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyStartD1;

    /** @var string - end time of the available D1 history (FXT) */
    protected $historyEndD1;

    /** @var DukascopySymbol [transient] - the Dukascopy symbol mapped to this Rosatrader symbol */
    protected $dukascopySymbol;


    /**
     * Return the instrument's quote resolution (the value of 1 point).
     *
     * @return double
     */
    public function getPointValue() {
        return 1/pow(10, $this->digits);
    }


    /**
     * Return the start time of the symbol's stored tick history (FXT).
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
     * Return the end time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndTick($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyEndTick) || $format=='Y-m-d H:i:s')
            return $this->historyEndTick;
        return gmdate($format, strtotime($this->historyEndTick.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored M1 history (FXT).
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
     * Return the end time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndM1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyEndM1) || $format=='Y-m-d H:i:s')
            return $this->historyEndM1;
        return gmdate($format, strtotime($this->historyEndM1.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored D1 history (FXT).
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
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndD1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyEndD1) || $format=='Y-m-d H:i:s')
            return $this->historyEndD1;
        return gmdate($format, strtotime($this->historyEndD1.' GMT'));
    }


    /**
     * Get the M1 history for a given day.
     *
     * @param  int  $time                 - FXT timestamp
     * @param  bool $optimized [optional] - returned bar format (see notes)
     *
     * @return array - An empty array if history for the specified time is not available. Otherwise a timeseries array with
     *                 each element describing a single price bar as follows:
     *
     * <pre>
     * $optimized => FALSE (default):
     * ------------------------------
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (float),          // open value in real terms
     *     'high'  => (float),          // high value in real terms
     *     'low'   => (float),          // low value in real terms
     *     'close' => (float),          // close value in real terms
     *     'ticks' => (int),            // volume (if available) or number of synthetic ticks
     * )
     *
     * $optimized => TRUE:
     * -------------------
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (int),            // open value in point
     *     'high'  => (int),            // high value in point
     *     'low'   => (int),            // low value in point
     *     'close' => (int),            // close value in point
     *     'ticks' => (int),            // volume (if available) or number of synthetic ticks
     * )
     * </pre>
     */
    public function getHistoryM1($time, $optimized = false) {
        $storage = $this->di('config')['app.dir.storage'];
        $dir = $storage.'/history/rosatrader/'.$this->type.'/'.$this->name.'/'.gmdate('Y/m/d', $time);

        if (!is_file($file=$dir.'/M1.bin') && !is_file($file.='.rar'))
            return [];

        $bars = RT::readBarFile($file, $this);
        if ($optimized)
            return $bars;

        $point = $this->getPointValue();

        foreach ($bars as $i => $v) {
            $bars[$i]['open' ] *= $point;
            $bars[$i]['high' ] *= $point;
            $bars[$i]['low'  ] *= $point;
            $bars[$i]['close'] *= $point;
        }
        return $bars;
    }


    /**
     * Whether the symbol is a Forex symbol.
     *
     * @return bool
     */
    public function isForex() {
        return ($this->type === self::TYPE_FOREX);
    }


    /**
     * Whether the symbol is a metals symbol.
     *
     * @return bool
     */
    public function isMetal() {
        return ($this->type === self::TYPE_METAL);
    }


    /**
     * Whether the symbol is a synthetic symbol.
     *
     * @return bool
     */
    public function isSynthetic() {
        return ($this->type === self::TYPE_SYNTHETIC);
    }


    /**
     * Whether a time is a trading day for the instrument in the standard timezone of the timestamp.
     *
     * @param  int $time - Unix or FXT timestamp
     *
     * @return bool
     */
    public function isTradingDay($time) {
        Assert::int($time);

        return ($time && !isWeekend($time) && !$this->isHoliday($time));
    }


    /**
     * Whether a time is on a Holiday for the instrument in the standard timezone of the timestamp.
     *
     * @param  int $time - Unix or FXT timestamp
     *
     * @return bool
     */
    public function isHoliday($time) {
        Assert::int($time);
        if (!$time)
            return false;

        if (isHoliday($time))                               // check for common Holidays
            return true;
        if ($this->isMetal() || $this->name=='XAUI') {
            if (isGoodFriday($time))                        // check for specific Holidays
                return true;
        }
        return false;
    }


    /**
     * Show history status information.
     *
     * @return bool - success status
     */
    public function showHistoryStatus() {
        $start      = $this->getHistoryStartM1('D, d-M-Y');
        $end        = $this->getHistoryEndM1  ('D, d-M-Y');
        $paddedName = str_pad($this->name, 6);

        if ($start) Output::out('[Info]    '.$paddedName.'  M1 local history from '.$start.' to '.$end);
        else        Output::out('[Info]    '.$paddedName.'  M1 local history empty');
        return true;
    }


    /**
     * Synchronize start/end times in the database with the files in the file system.
     *
     * @return bool - success status
     */
    public function synchronizeHistory() {
        $storageDir  = $this->di('config')['app.dir.storage'];
        $storageDir .= '/history/rosatrader/'.$this->type.'/'.$this->name;
        $paddedName  = str_pad($this->name, 6);
        $startDate   = $endDate = null;
        $missing     = [];

        // find the oldest existing history file
        $years = glob($storageDir.'/[12][0-9][0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
        foreach ($years as $year) {
            $months = glob($year.'/[0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
            foreach ($months as $month) {
                $days = glob($month.'/[0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
                foreach ($days as $day) {
                    if (is_file($file=$day.'/M1.bin') || is_file($file.='.rar')) {
                        $startDate = strtotime(strRight($day, 10).' GMT');
                        break 3;
                    }
                }
            }
        }

        // iterate over the whole time range and check existing files
        if ($startDate) {                                                               // 00:00 FXT of the first day
            $today     = ($today=fxTime()) - $today%DAY;                                // 00:00 FXT of the current day
            $delMsg    = '[Info]    '.$paddedName.'  deleting non-trading day M1 file: ';
            $yesterDay = fxTime() - fxTime()%DAY - DAY;

            $missMsg = function($missing) use ($paddedName, $yesterDay) {
                if ($misses = sizeof($missing)) {
                    $first = first($missing);
                    $last  = last($missing);
                    Output::out('[Info]    '.$paddedName.'  '.$misses.' missing history file'.pluralize($misses)
                                            .($misses==1 ? ' for '.gmdate('D, d-M-Y', $first)
                                                         : ' from '.gmdate('D, d-M-Y', $first).' until '.($last==$yesterDay? 'now' : gmdate('D, d-M-Y', $last))));
                }
            };

            for ($day=$startDate; $day < $today; $day+=1*DAY) {
                $dir = $storageDir.'/'.gmdate('Y/m/d', $day);

                if ($this->isTradingDay($day)) {
                    if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar')) {
                        $endDate = $day;
                        if ($missing) {
                            $missMsg($missing);
                            $missing = [];
                        }
                    }
                    else {
                        $missing[] = $day;
                    }
                }
                else {
                    if (is_file($file = $dir . '/M1.bin')) {
                        Output::out($delMsg . RT::relativePath($file));
                        unlink($file);
                    }
                    if (is_file($file = $dir . '/M1.bin.rar')) {
                        Output::out($delMsg.RT::relativePath($file));
                        unlink($file);
                    }
                }
            }
            if ($missing) $missMsg($missing);
        }

        // update the database
        if ($startDate != $this->getHistoryStartM1('U')) {
            Output::out('[Info]    '.$paddedName.'  updating history start time to '.($startDate ? gmdate('D, d-M-Y H:i', $startDate) : '(empty)'));
            $this->historyStartM1 = $startDate ? gmdate('Y-m-d H:i:s', $startDate) : null;
            $this->modified();
        }

        if ($endDate) {
            $endDate += 23*HOURS + 59*MINUTES;          // adjust to the last minute as the database always holds full days
        }
        if ($endDate != $this->getHistoryEndM1('U')) {
            Output::out('[Info]    '.$paddedName.'  updating history end time to: '.($endDate ? gmdate('D, d-M-Y H:i', $endDate) : '(empty)'));
            $this->historyEndM1 = $endDate ? gmdate('Y-m-d H:i:s', $endDate) : null;
            $this->modified();
        }
        $this->save();

        !$missing && Output::out('[Info]    '.$paddedName.'  '.($startDate ? 'ok':'empty'));
        Output::out('---------------------------------------------------------------------------------------');
        return true;
    }


    /**
     * Update the symbol's history.
     *
     * @param  int $period [optional] - bar period identifier
     *
     * @return bool - success status
     */
    public function updateHistory($period = PERIOD_M1) {
        $provider = $this->getHistorySource();
        if (!$provider) {
            Output::error('[Error]   '.str_pad($this->name, 6).'  no history provider found');
            return false;
        }

        $historyEnd = (int) $this->getHistoryEndM1('U');
        $updateFrom = $historyEnd ? $historyEnd + 1*DAY : 0;                        // the day after history ends
        $today      = ($today=fxTime()) - $today%DAY;                               // 00:00 FXT
        $status = null;

        for ($day=$updateFrom; $day < $today; $day+=1*DAY) {
            if ($day && !$this->isTradingDay($day))                                 // skip non-trading days
                continue;
            !$status && Output::out($status='[Info]    '.str_pad($this->name, 6).'  updating M1 history since '.($day ? gmdate('D, d-M-Y', $historyEnd) : 'start'));

            $bars = $provider->getHistory($period, $day, true);
            if (!$bars) {
                Output::error('[Error]   '.str_pad($this->name, 6).'  M1 history '.($day ? ' for '.gmdate('D, d-M-Y', $day) : '').' not available');
                return false;
            }
            RT::saveM1Bars($bars, $this);

            if (!$day) {                                                            // If $day was zero (full update since start)
                $day = $bars[0]['time'];                                            // adjust it to the first available history
                $this->historyStartM1 = gmdate('Y-m-d H:i:s', $bars[0]['time']);    // returned and update metadata *after* history
            }                                                                       // was successfully stored.
            $this->historyEndM1 = gmdate('Y-m-d H:i:s', $bars[sizeof($bars)-1]['time']);
            $this->modified()->save();                                              // update the database

            Process::dispatchSignals();
        }
        Output::out('[Ok]      '.$this->name);
        return true;
    }


    /**
     * Return a {@link \rosasurfer\rt\lib\IHistorySource} for the symbol.
     *
     * @return IHistorySource|null
     */
    public function getHistorySource() {
        if ($this->isSynthetic())
            return $this->getSynthesizer();
        return $this->getDukascopySymbol();
    }


    /**
     * Look-up and instantiate a {@link \rosasurfer\rt\lib\synthetic\ISynthesizer} to calculate quotes of a synthetic instrument.
     *
     * @return ISynthesizer
     */
    protected function getSynthesizer() {
        if (!$this->isSynthetic()) throw new RuntimeException('Cannot create Synthesizer for non-synthetic instrument');

        $customClass = strLeftTo(ISynthesizer::class, '\\', -1).'\\index\\'.$this->name;
        if (is_class($customClass) && is_a($customClass, ISynthesizer::class, true))
            return new $customClass($this);
        return new GenericSynthesizer($this);
    }
}
