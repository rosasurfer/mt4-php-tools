<?php
namespace rosasurfer\rt\model;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\FXT;
use rosasurfer\rt\lib\Rost;
use rosasurfer\rt\lib\RT;
use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\lib\synthetic\DefaultSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\isGoodFriday;
use function rosasurfer\rt\isHoliday;
use function rosasurfer\rt\isWeekend;

use const rosasurfer\rt\PERIOD_M1;


/**
 * Represents a Rosatrader symbol.
 *
 * @method string          getType()            Return the instrument type (forex|metals|synthetic).
 * @method string          getName()            Return the symbol name, i.e. the actual symbol.
 * @method string          getDescription()     Return the symbol description.
 * @method int             getDigits()          Return the number of fractional digits of symbol prices.
 * @method bool            isAutoUpdate()       Whether automatic history updates are enabled.
 * @method string          getFormula()         Return a synthetic instrument's calculation formula (LaTeX).
 * @method DukascopySymbol getDukascopySymbol() Return the {@link DukascopySymbol} mapped to this RosaTrader symbol.
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

    /** @var string - symbol name */
    protected $name;

    /** @var string - symbol description */
    protected $description;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var bool - whether automatic history updates are enabled */
    protected $autoUpdate;

    /** @var string - LaTeX formula for calculation of synthetic instruments */
    protected $formula;

    /** @var string - start time of the available tick history (FXT) */
    protected $historyTicksStart;

    /** @var string - end time of the available tick history (FXT) */
    protected $historyTicksEnd;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyM1Start;

    /** @var string - end time of the available M1 history (FXT) */
    protected $historyM1End;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyD1Start;

    /** @var string - end time of the available D1 history (FXT) */
    protected $historyD1End;

    /** @var DukascopySymbol [transient] - the Dukascopy symbol mapped to this RosaTrader symbol */
    protected $dukascopySymbol;


    /**
     * Return the instrument's quote resolution (the value of 1 point).
     *
     * @return double
     */
    public function getPoint() {
        return 1/pow(10, $this->digits);
    }


    /**
     * Return the start time of the symbol's stored tick history (FXT).
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
     * Return the end time of the symbol's stored tick history (FXT).
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
     * Return the start time of the symbol's stored M1 history (FXT).
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
     * Return the end time of the symbol's stored M1 history (FXT).
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
     * Return the start time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryD1Start($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyD1Start) || $format=='Y-m-d H:i:s')
            return $this->historyD1Start;
        return gmdate($format, strtotime($this->historyD1Start.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryD1End($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyD1End) || $format=='Y-m-d H:i:s')
            return $this->historyD1End;
        return gmdate($format, strtotime($this->historyD1End.' GMT'));
    }


    /**
     * Get the M1 history for the specified day.
     *
     * @param  int $fxDay - FXT timestamp
     *
     * @return array[] - If history for the specified day is not available an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a M1 bar as follows:
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
    public function getHistoryM1($fxDay) {
        $dataDir  = $this->di()['config']['app.dir.data'];
        $dataDir .= '/history/rosatrader/'.$this->type.'/'.$this->name;
        $dir      = $dataDir.'/'.gmdate('Y/m/d', $fxDay);

        if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar'))
            return RT::readBarFile($file, $this);
        return [];
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
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        return (!isWeekend($time) && !$this->isHoliday($time));
    }


    /**
     * Whether a time is on a Holiday for the instrument in the standard timezone of the timestamp.
     *
     * @param  int $time - Unix or FXT timestamp
     *
     * @return bool
     */
    public function isHoliday($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        if (isHoliday($time))                           // check for common Holidays
            return true;
        if ($this->isMetal() && isGoodFriday($time))    // check for specific Holidays
            return true;
        return false;
    }


    /**
     * Show history status information.
     *
     * @return bool - success status
     */
    public function showHistoryStatus() {
        $start = $this->getHistoryM1Start('D, d-M-Y');
        $end   = $this->getHistoryM1End('D, d-M-Y');

        if ($start) {
            echoPre('[Info]    '.$this->name.'  M1 history from '.$start.' FXT');
            echoPre('[Info]    '.$this->name.'  M1 history to   '.$end.' FXT');
        }
        else {
            echoPre('[Info]    '.$this->name.'  M1 history empty');
        }
        return true;
    }


    /**
     * Synchronize the history in the file system with the database.
     *
     * @return bool - success status
     */
    public function synchronizeHistory() {
        $dataDir  = $this->di()['config']['app.dir.data'];
        $dataDir .= '/history/rosatrader/'.$this->type.'/'.$this->name;

        echoPre('[Info]    '.$this->name.'  synchronizing history');

        $startDate = $endDate = null;
        $errors  = false;
        $missing = [];

        // find the oldest existing history file
        $years = glob($dataDir.'/[12][0-9][0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
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
            $today  = ($today=fxTime()) - $today%DAY;                                   // 00:00 FXT of the current day
            $delMsg = '[Info]    '.$this->name.'  deleting obsolete M1 file: ';

            $missMsg = function($missing) {
                if ($misses = sizeof($missing)) {
                    ($misses > 2) && echoPre('[Error]   '.$this->name.'  ...');
                    echoPre('[Error]   '.$this->name.'  '.($misses > 2 ? $misses : '').' missing history file'.($misses==2 ? ' for':'s until').' '.gmdate('D, Y-m-d', last($missing)));
                }
            };

            for ($day=$startDate; $day < $today; $day+=1*DAY) {
                $dir = $dataDir.'/'.gmdate('Y/m/d', $day);

                if ($this->isTradingDay($day)) {
                    if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar')) {
                        $endDate = $day;
                        if ($missing) {
                            $missMsg($missing);
                            $missing = [];
                        }
                    }
                    else {
                        !$missing && echoPre('[Error]   '.$this->name.'  missing history file for '.gmdate('D, Y-m-d', $day));
                        $errors = (bool)$missing[] = $day;
                    }
                }
                else {
                    is_file($file=$dir.'/M1.bin'    ) && true(echoPre($delMsg.Rost::relativePath($file))) && unlink($file);
                    is_file($file=$dir.'/M1.bin.rar') && true(echoPre($delMsg.Rost::relativePath($file))) && unlink($file);
                }
            }
            $missing && $missMsg($missing);
        }

        // update the database
        if ($startDate != $this->getHistoryM1Start('U')) {
            echoPre('[Info]    '.$this->name.'  updating start time to '.($startDate ? gmdate('Y-m-d', $startDate) : '(empty)'));
            $this->historyM1Start = $startDate ? gmdate('Y-m-d H:i:s', $startDate) : null;
            $this->modified();
        }
        if ($endDate != $this->getHistoryM1End('U')) {
            echoPre('[Info]    '.$this->name.'  updating end time to '.($endDate ? gmdate('Y-m-d', $endDate) : '(empty)'));
            $this->historyM1End = $endDate ? gmdate('Y-m-d H:i:s', $endDate) : null;
            $this->modified();
        }
        $this->save();

        echoPre('[Info]    '.$this->name.'  '.($errors ? 'done':'ok'));
        return true;
    }


    /**
     * Discard existing history and reload or recreate it.
     *
     * @return bool - success status
     */
    public function refreshHistory() {
        throw new UnimplementedFeatureException(__METHOD__.'() not implemented');
        return false;
    }


    /**
     * Update the symbol's history.
     *
     * @return bool - success status
     */
    public function updateHistory() {
        $updatedTo  = (int) $this->getHistoryM1End('U');                            // 00:00 FXT of the last existing day
        $updateFrom = $updatedTo ? $updatedTo - $updatedTo%DAY + 1*DAY : 0;         // 00:00 FXT of the first day to update
        $today      = ($today=fxTime()) - $today%DAY;                               // 00:00 FXT of the current day
        echoPre('[Info]    '.$this->name.'  updating M1 history '.($updatedTo ? 'since '.gmdate('D, d-M-Y', $updatedTo) : 'from start'));

        /** @var Synthesizer     $synthesizer */
        /** @var DukascopySymbol $dukaSymbol  */
        $synthesizer = $dukaSymbol = null;

        if      ($this->isSynthetic())                       $synthesizer = $this->getSynthesizer();
        else if (!$dukaSymbol = $this->getDukascopySymbol()) return false(echoPre('[Error]   '.$this->name.'  Dukascopy mapping not found'));

        for ($day=$updateFrom; $day < $today; $day+=1*DAY) {
            if ($day && !$this->isTradingDay($day))                             // skip non-trading days
                continue;
            $bars = $this->isSynthetic() ? $synthesizer->calculateQuotes($day) : $dukaSymbol->getHistory(PERIOD_M1, $day);
            if (!$bars) return false(echoPre('[Error]   '.$this->name.'  M1 history sources'.($day ? ' for '.gmdate('D, d-M-Y', $day) : '').' not available'));
            if (!$day) {
                $opentime = $bars[0]['time'];                                   // if $day was zero (full update since start)
                $day = $opentime - $opentime%DAY;                               // adjust it to the first available history
            }
            RT::saveM1Bars($bars, $this);                                       // store the quotes

            if (!$this->historyM1Start)                                         // update metadata *after* history was successfully saved
                $this->historyM1Start = gmdate('Y-m-d H:i:s', $day);
            $this->historyM1End = gmdate('Y-m-d H:i:s', $day);
            $this->modified()->save();                                          // update the database
            Process::dispatchSignals();                                         // process signals
        }

        echoPre('[Ok]      '.$this->name);
        return true;
    }


    /**
     * Look-up and instantiate a {@link Synthesizer} to calculate quotes of a synthetic instrument.
     *
     * @return Synthesizer
     */
    protected function getSynthesizer() {
        if (!$this->isSynthetic()) throw new RuntimeException('Cannot create Synthesizer for non-synthetic instrument');

        $customClass = strLeftTo(Synthesizer::class, '\\', -1).'\\index\\'.$this->name;
        if (is_class($customClass) && is_a($customClass, Synthesizer::class, $allowString=true))
            return new $customClass($this);
        return new DefaultSynthesizer($this);
    }
}
