<?php
namespace rosasurfer\rt\model;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\process\Process;

use rosasurfer\rt\FXT;
use rosasurfer\rt\Rost;
use rosasurfer\rt\RT;
use rosasurfer\rt\synthetic\DefaultSynthesizer;
use rosasurfer\rt\synthetic\SynthesizerInterface as Synthesizer;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\isGoodFriday;
use function rosasurfer\rt\isHoliday;
use function rosasurfer\rt\isWeekend;


/**
 * Represents a Rosatrader symbol.
 *
 * @method string          getType()            Return the instrument type (forex|metals|synthetic).
 * @method string          getName()            Return the symbol name, i.e. the actual symbol.
 * @method string          getDescription()     Return the symbol description.
 * @method int             getDigits()          Return the number of fractional digits of symbol prices.
 * @method bool            isAutoUpdate()       Whether automatic history updates are enabled.
 * @method string          getFormula()         Return a synthetic instrument's calculation formula (LaTeX).
 * @method DukascopySymbol getDukascopySymbol() Return the Dukascopy symbol mapped to this RosaTrader symbol.
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
        if (!isSet($this->historyTicksStart) || $format=='Y-m-d H:i:s')
            return $this->historyTicksStart;
        return gmDate($format, strToTime($this->historyTicksStart.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryTicksEnd($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyTicksEnd) || $format=='Y-m-d H:i:s')
            return $this->historyTicksEnd;
        return gmDate($format, strToTime($this->historyTicksEnd.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyM1Start) || $format=='Y-m-d H:i:s')
            return $this->historyM1Start;
        return gmDate($format, strToTime($this->historyM1Start.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryM1End($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyM1End) || $format=='Y-m-d H:i:s')
            return $this->historyM1End;
        return gmDate($format, strToTime($this->historyM1End.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryD1Start($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyD1Start) || $format=='Y-m-d H:i:s')
            return $this->historyD1Start;
        return gmDate($format, strToTime($this->historyD1Start.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryD1End($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyD1End) || $format=='Y-m-d H:i:s')
            return $this->historyD1End;
        return gmDate($format, strToTime($this->historyD1End.' GMT'));
    }


    /**
     * Get the M1 history for the specified day.
     *
     * @param  int $fxDay - FXT timestamp
     *
     * @return array[] - If history for the specified day is not available an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a M1 bar as following:
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
        $dir      = $dataDir.'/'.gmDate('Y/m/d', $fxDay);

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
        return $this->type === self::TYPE_FOREX;
    }


    /**
     * Whether the symbol is a metals symbol.
     *
     * @return bool
     */
    public function isMetal() {
        return $this->type === self::TYPE_METAL;
    }


    /**
     * Whether the symbol is a synthetic symbol.
     *
     * @return bool
     */
    public function isSynthetic() {
        return $this->type === self::TYPE_SYNTHETIC;
    }


    /**
     * Whether a time is a trading day for the instrument in the standard timezone of the timestamp.
     *
     * @param  int $time - Unix or FXT timestamp
     *
     * @return bool
     */
    public function isTradingDay($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

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
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

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
            echoPre('[Info]    '.$this->name.'  M1 history from '.$start);
            echoPre('[Info]    '.$this->name.'  M1 history to   '.$end);
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
                        $startDate = strToTime(strRight($day, 10).' GMT');
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
                if ($misses = sizeOf($missing)) {
                    ($misses > 2) && echoPre('[Error]   '.$this->name.'  ...');
                    echoPre('[Error]   '.$this->name.'  '.($misses > 2 ? $misses : '').' missing history file'.($misses==2 ? ' for':'s until').' '.gmDate('D, Y-m-d', last($missing)));
                }
            };

            for ($day=$startDate; $day < $today; $day+=1*DAY) {
                $dir = $dataDir.'/'.gmDate('Y/m/d', $day);

                if ($this->isTradingDay($day)) {
                    if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar')) {
                        $endDate = $day;
                        if ($missing) {
                            $missMsg($missing);
                            $missing = [];
                        }
                    }
                    else {
                        !$missing && echoPre('[Error]   '.$this->name.'  missing history file for '.gmDate('D, Y-m-d', $day));
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
            echoPre('[Info]    '.$this->name.'  updating start time to '.($startDate ? gmDate('Y-m-d', $startDate) : '(empty)'));
            $this->historyM1Start = $startDate ? gmDate('Y-m-d H:i:s', $startDate) : null;
            $this->modified();
        }
        if ($endDate != $this->getHistoryM1End('U')) {
            echoPre('[Info]    '.$this->name.'  updating end time to '.($endDate ? gmDate('Y-m-d', $endDate) : '(empty)'));
            $this->historyM1End = $endDate ? gmDate('Y-m-d H:i:s', $endDate) : null;
            $this->modified();
        }
        if ($this->isModified()) $this->save();
        else                     echoPre('[Info]    '.$this->name.'  '.($errors ? 'done':'ok'));

        return true;
    }


    /**
     * Discard existing history and reload or recreate it.
     *
     * @return bool - success status
     */
    public function refreshHistory() {
        return false;
    }


    /**
     * Update the symbol's history (at the moment only M1 history is updated).
     *
     * @return bool - success status
     */
    public function updateHistory() {
        $updatedTo  = (int) $this->getHistoryM1End('U');                            // 00:00 FXT of the last existing day
        $updateFrom = $updatedTo ? $updatedTo - $updatedTo%DAY + 1*DAY : 0;         // 00:00 FXT of the first day to update
        $today      = ($today=fxTime()) - $today%DAY;                               // 00:00 FXT of the current day
        echoPre('[Info]    '.$this->name.'  updating M1 history '.($updatedTo ? 'since '.gmDate('D, d-M-Y', $updatedTo) : 'from start'));

        if ($this->isSynthetic()) {
            /** @var Synthesizer $synthesizer */
            $synthesizer = $this->getSynthesizer();

            for ($day=$updateFrom; $day < $today; $day+=1*DAY) {
                if ($day && !$this->isTradingDay($day))                             // skip non-trading days
                    continue;

                $bars = $synthesizer->calculateQuotes($day);
                if (!$bars) return false(echoPre('[Error]   '.$this->name.'  M1 history sources'.($day ? ' for '.gmDate('D, d-M-Y', $day) : '').' not available'));
                if (!$day) {
                    $opentime = $bars[0]['time'];                                   // if $day is zero (complete update since start)
                    $day = $opentime - $opentime%DAY;                               // adjust it to the first available history
                }
                RT::saveM1Bars($bars, $this);                                       // store the quotes

                if (!$this->historyM1Start)
                    $this->historyM1Start = gmDate('Y-m-d H:i:s', $day);
                $this->historyM1End = gmDate('Y-m-d H:i:s', $day);                  // update the database
                $this->modified();
                $this->save();

                Process::dispatchSignals();                                         // check for Ctrl-C
            }
        }
        else {
            // request price updates from a mapped symbol's data source
            throw new UnimplementedFeatureException('RosaSymbol::updateHistory() not yet implemented for regular instruments ('.$this->name.')');
        }
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
