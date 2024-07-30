<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\ministruts\core\CObject;
use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\error\ErrorHandler;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\FileNotFoundException;
use rosasurfer\ministruts\core\exception\IllegalStateException;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;
use rosasurfer\ministruts\file\FileSystem as FS;

use rosasurfer\rt\lib\Rost;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\strCompareI;

use function rosasurfer\rt\periodDescription;
use function rosasurfer\rt\timeframeDescription;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\DAYS;
use const rosasurfer\ministruts\MINUTE;
use const rosasurfer\ministruts\MINUTES;
use const rosasurfer\ministruts\NL;
use const rosasurfer\ministruts\WEEK;

use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_M5;
use const rosasurfer\rt\PERIOD_M15;
use const rosasurfer\rt\PERIOD_M30;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_H4;
use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PERIOD_W1;
use const rosasurfer\rt\PERIOD_MN1;


/**
 * Object wrapping a single MT4 history file ("*.hst").
 */
class HistoryFile extends CObject {


    /** @var ?resource - handle of an open history file */
    protected $hFile = null;

    /** @var string - simple history file name (basename + extension) */
    protected $fileName;

    /** @var string - server name */
    protected $serverName;

    /** @var string - full server directory name */
    protected $serverDirectory;

    /** @var bool - whether the history file is closed and all instance resources are released */
    protected $closed = false;


    /** @var HistoryHeader */
    protected $hstHeader;

    /** @var int - history file timeframe */
    protected $period;

    /** @var int - e.g. if Digits=2 then pointsPerUnit=100 */
    protected $pointsPerUnit;

    /** @var float - e.g. if Digits=2 then pointSize=0.01 */
    protected $pointSize;


    /** @var string - pack() format string */
    protected $barPackFormat;

    /** @var string - unpack() format string */
    protected $barUnpackFormat;

    /** @var int - bar size in bytes according to the data format */
    protected $barSize = 0;

    /** @var array[] - internal write buffer (ROST_PRICE_BAR[]) */
    public $barBuffer = [];

    /** @var int - internal write buffer default size */
    protected $barBufferSize = 10000;


    // Metadata of stored bars

    /** @var int - number of stored bars */
    protected $stored_bars = 0;

    /** @var int - offset of the first stored bar */
    protected $stored_from_offset = -1;

    /** @var int - open time of the first stored bar */
    protected $stored_from_openTime = 0;

    /** @var int - close time of the first stored bar */
    protected $stored_from_closeTime = 0;

    /** @var int - offset of the last stored bar */
    protected $stored_to_offset = -1;

    /** @var int - open time of the last stored bar */
    protected $stored_to_openTime = 0;

    /** @var int - close time of the last stored bar */
    protected $stored_to_closeTime = 0;

    /** @var int - time until stored bar data is synchronized (last synchronization time) */
    protected $stored_lastSyncTime = 0;


    // Metadata of stored and unstored (buffered) bars

    /** @var int - number of stored and unstored (buffered) bars */
    protected $full_bars = 0;

    /** @var int - offset of the first bar (incl. buffered bars) */
    protected $full_from_offset = -1;

    /** @var int - open time of the first bar (incl. buffered bars) */
    protected $full_from_openTime = 0;

    /** @var int - close time of the first bar (incl. buffered bars) */
    protected $full_from_closeTime = 0;

    /** @var int - offset of the last bar (incl. buffered bars) */
    protected $full_to_offset = -1;

    /** @var int - open time of the last bar (incl. buffered bars) */
    protected $full_to_openTime = 0;

    /** @var int - close time of the last bar (incl. buffered bars) */
    protected $full_to_closeTime = 0;

    /** @var int - time until all bar data is synchronized (last synchronization time incl. buffered bars) */
    protected $full_lastSyncTime = 0;


    /** @var int - open time of the lats added M1 data; used for validation in $this->append*() */
    protected $lastM1DataTime = 0;


    /**
     * Return the simple history file name (basename + extension).
     *
     * @return string
     */
    public function getFileName() {
        return $this->fileName;
    }


    /**
     * Return the history file's server name.
     *
     * @return string
     */
    public function getServerName() {
        return $this->serverName;
    }


    /**
     * Return the history file's full server directory name.
     *
     * @return string
     */
    public function getServerDirectory() {
        return $this->serverDirectory;
    }


    /**
     * Whether the history file is closed and all instance resources are released.
     *
     * @return bool
     */
    public function isClosed() {
        return (bool)$this->closed;
    }


    /**
     * Return the history file's last synchronization time (stored and unstored bars).
     *
     * @return int
     */
    public function getLastSyncTime() {
        return $this->full_lastSyncTime;
    }


    /**
     * Return the history file's point size (e.g. if dgits=2 then pointSize=0.01).
     *
     * @return float
     */
    public function getPointSize() {
        return $this->pointSize;
    }


    /**
     * Return the history file's points per unit (e.g. if digits=2 then pointsPerUnit=100).
     *
     * @return int
     */
    public function getPointsPerUnit() {
        return $this->pointsPerUnit;
    }


    /**
     * Return the history file's header.
     *
     * @return HistoryHeader
     */
    public function getHistoryHeader() {
        return $this->hstHeader;
    }


    /**
     * Return the history file's format identifier as stored in the {@link HistoryHeader}.
     *
     * @return int
     */
    public function getVersion() {
        return $this->hstHeader->getFormat();
    }


    /**
     * Return the history file's symbol as stored in the {@link HistoryHeader}.
     *
     * @return string
     */
    public function getSymbol() {
        return $this->hstHeader->getSymbol();
    }


    /**
     * Return the history file's timeframe as stored in the {@link HistoryHeader}.
     *
     * @return int
     */
    public function getPeriod() {
        return $this->hstHeader->getPeriod();
    }


    /**
     * Alias of HistoryFile::getPeriod().
     *
     * Return the history file's timeframe as stored in the {@link HistoryHeader}.
     *
     * @return int
     */
    public function getTimeframe() {
        return $this->hstHeader->getPeriod();
    }


    /**
     * Return the history file's digits value as stored in the {@link HistoryHeader}.
     *
     * @return int
     */
    public function getDigits() {
        return $this->hstHeader->getDigits();
    }


    /**
     * Return the history file's synchronization marker as stored in the {@link HistoryHeader}.
     *
     * @return int
     */
    public function getSyncMarker() {
        return $this->hstHeader->getSyncMarker();
    }


    /**
     * Overloaded constructor with the following method signatures:
     *
     *  new HistoryFile($fileName)                                               <br>
     *  new HistoryFile($symbol, $timeframe, $digits, $format, $serverDirectory) <br>
     *
     * @param  mixed ...$args
     */
    public function __construct(...$args) {
        $argc = sizeof($args);
        if      ($argc == 1) $this->__construct1(...$args);
        else if ($argc == 5) $this->__construct2(...$args);
        else throw new InvalidValueException('Invalid number of arguments: '.$argc);
    }


    /**
     * Overloaded constructor.
     *
     * Create a new instance from an existing MT4 history file. Existing data is kept.
     *
     * @param  string $fileName - MT4 history file name
     *
     * @return void
     */
    private function __construct1($fileName) {
        Assert::string($fileName);
        if (!is_file($fileName)) throw new FileNotFoundException('Invalid parameter $fileName: "'.$fileName.'" (file not found)');

        // resolve directory, file and server name
        $realName              = realpath($fileName);
        $this->fileName        = basename($realName);
        $this->serverDirectory = dirname ($realName);
        $this->serverName      = basename($this->serverDirectory);

        // validate the file size
        $fileSize = filesize($fileName);
        if ($fileSize < HistoryHeader::SIZE) throw new MetaTraderException(
            'Invalid or unsupported format of "'.$fileName.'": filesize='.$fileSize.' (minFileSize='.HistoryHeader::SIZE.')',
            MetaTraderException::ERR_FILESIZE_INSUFFICIENT
        );

        // open file and read/validate the header
        $this->hFile     = fopen($fileName, 'r+b');               // FILE_READ|FILE_WRITE
        $this->hstHeader = new HistoryHeader(fread($this->hFile, HistoryHeader::SIZE));

        if (!strCompareI($this->fileName, $this->getSymbol().$this->getTimeframe().'.hst')) throw new MetaTraderException('filename.mis-match: File name/symbol mis-match of "'.$fileName.'": header="'.$this->getSymbol().','.timeframeDescription($this->getTimeframe()).'"');
        $barSize = $this->getVersion()==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        if ($trailing=($fileSize-HistoryHeader::SIZE) % $barSize)                           throw new MetaTraderException('filesize.trailing: Corrupted file "'.$fileName.'": '.$trailing.' trailing bytes');

        // read and initialize the file's metadata
        $this->initMetaData();
    }


    /**
     * Overloaded constructor.
     *
     * Create a new instance and reset an existing MT4 history file. Existing data is dismissed.
     *
     * @param  string $symbol          - symbol
     * @param  int    $timeframe       - timeframe
     * @param  int    $digits          - digits
     * @param  int    $format          - file format: 400=MT4 <= build 509; 401=MT4 > build 509
     * @param  string $serverDirectory - full server directory (storage location)
     *
     * @return void
     */
    private function __construct2($symbol, $timeframe, $digits, $format, $serverDirectory) {
        Assert::string($symbol, '$symbol');
        if (!strlen($symbol))                         throw new InvalidValueException('Invalid parameter $symbol: ""');
        if (strlen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidValueException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
        Assert::int($timeframe, '$timeframe');
        if (!MT4::isStdTimeframe($timeframe))         throw new InvalidValueException('Invalid parameter $timeframe: '.$timeframe.' (not a MetaTrader standard timeframe)');
        Assert::string($serverDirectory, '$serverDirectory');
        if (!is_dir($serverDirectory))                throw new InvalidValueException('Directory "'.$serverDirectory.'" not found');

        $this->hstHeader       = new HistoryHeader($format, null, $symbol, $timeframe, $digits, null, null);
        $this->serverDirectory = realpath($serverDirectory);
        $this->serverName      = basename($this->serverDirectory);
        $this->fileName        = $symbol.$timeframe.'.hst';

        // rewrite history file and header
        FS::mkDir($this->serverDirectory);
        $fileName    = $this->serverDirectory.'/'.$this->fileName;
        $this->hFile = fopen($fileName, 'wb');                      // FILE_WRITE
        $this->writeHistoryHeader();

        // read and initialize the file's metadata
        $this->initMetaData();
    }


    /**
     * Read the file's metadata and initialize local vars. Called only by a constructor.
     *
     * @return void
     */
    private function initMetaData() {
        $this->period          = $this->hstHeader->getPeriod();
        $this->barSize         = $this->getVersion()==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        $this->barPackFormat   = MT4::BAR_getPackFormat($this->getVersion());
        $this->barUnpackFormat = MT4::BAR_getUnpackFormat($this->getVersion());

        $this->pointsPerUnit = pow(10, $this->getDigits());
        $this->pointSize     = 1/$this->pointsPerUnit;

        $fileSize = filesize($this->serverDirectory.'/'.$this->fileName);
        if ($fileSize > HistoryHeader::SIZE) {
            $bars    = ($fileSize-HistoryHeader::SIZE) / $this->barSize;
            fflush($this->hFile);
            $barFrom = $barTo = unpack($this->barUnpackFormat, fread($this->hFile, $this->barSize));
            if ($bars > 1) {
                fseek($this->hFile, HistoryHeader::SIZE + ($bars-1)*$this->barSize);
                $barTo = unpack($this->barUnpackFormat, fread($this->hFile, $this->barSize));
            }
            $from_offset    = 0;
            $from_openTime  = $barFrom['time'];
            $from_closeTime = Rost::periodCloseTime($from_openTime, $this->period);

            $to_offset      = $bars-1;
            $to_openTime    = $barTo['time'];
            $to_closeTime   = Rost::periodCloseTime($to_openTime, $this->period);

            // metadata: stored bars
            $this->stored_bars           = $bars;
            $this->stored_from_offset    = $from_offset;
            $this->stored_from_openTime  = $from_openTime;
            $this->stored_from_closeTime = $from_closeTime;
            $this->stored_to_offset      = $to_offset;
            $this->stored_to_openTime    = $to_openTime;
            $this->stored_to_closeTime   = $to_closeTime;
            $this->stored_lastSyncTime   = $this->hstHeader->getLastSyncTime();

            // metadata: stored and unstored (buffered) bars
            $this->full_bars             = $this->stored_bars;
            $this->full_from_offset      = $this->stored_from_offset;
            $this->full_from_openTime    = $this->stored_from_openTime;
            $this->full_from_closeTime   = $this->stored_from_closeTime;
            $this->full_to_offset        = $this->stored_to_offset;
            $this->full_to_openTime      = $this->stored_to_openTime;
            $this->full_to_closeTime     = $this->stored_to_closeTime;
            $this->full_lastSyncTime     = $this->stored_lastSyncTime;

            $this->lastM1DataTime = max($to_closeTime, $this->stored_lastSyncTime) - 1*MINUTE;  // the last bar might not yet be finished
        }
    }


    /**
     * Destructor
     *
     * Make sure the write buffer is emptied and the file is closed.
     */
    public function __destruct() {
        try {
            !$this->isClosed() && $this->close();
        }
        catch (\Throwable $ex) {
            throw ErrorHandler::handleDestructorException($ex);
        }
    }


    /**
     * Close the HistoryFile and release allocated resources. Afterwards the instance cannot be used anymore.
     *
     * @return bool - success status; FALSE if the instance was already closed
     */
    public function close() {
        if ($this->isClosed())
            return false;

        // empty the bar buffer
        if ($this->barBuffer) {
            $this->flush();
        }

        // close the file
        if (is_resource($this->hFile)) {
            $hTmp = $this->hFile;
            $this->hFile = null;
            fclose($hTmp);
        }
        return $this->closed=true;
    }


    /**
     * Set the size of the write buffer.
     *
     * @param  int $size - buffer size
     *
     * @return void
     */
    public function setBarBufferSize($size) {
        if ($this->closed) throw new IllegalStateException('Cannot process a closed '.get_class($this));
        Assert::int($size);
        if ($size < 0)     throw new InvalidValueException('Invalid parameter $size: '.$size);

        $this->barBufferSize = $size;
    }


    /**
     * Return the bar at the specified bar offset.
     *
     * @param  int $offset
     *
     * @return array|null - ROST_PRICE_BAR if the bar was not yet stored and is returned from the write buffer
     *                      HISTORY_BAR    if the bar was stored and is returned from the history file
     *                      NULL           if no such bar exists (offset is larger than the file's number of bars)
     *
     * @see  HistoryFile::getRosatraderBar()
     * @see  HistoryFile::getHistoryBar()
     */
    public function getBar($offset) {
        Assert::int($offset);
        if ($offset < 0) throw new InvalidValueException('Invalid parameter $offset: '.$offset);

        if ($offset >= $this->full_bars)                                        // bar[$offset] does not exist
            return null;

        if ($offset > $this->stored_to_offset)                                  // bar[$offset] is a buffered bar (ROST_PRICE_BAR)
            return $this->barBuffer[$offset-$this->stored_to_offset-1];

        fflush($this->hFile);
        fseek($this->hFile, HistoryHeader::SIZE + $offset*$this->barSize);      // bar[$offset] is a stored bar (HISTORY_BAR)
        return unpack($this->barUnpackFormat, fread($this->hFile, $this->barSize));
    }


    /**
     * Return the offset of the bar matching the specified open time. This is the bar position a bar with the specified open
     * time would be inserted.
     *
     * @param  int $time - time
     *
     * @return int - Offset or -1 if the time is younger than the youngest bar. To write a bar at offset -1 the history file
     *               has to be expanded.
     */
    public function findTimeOffset($time) {
        Assert::int($time);

        $size    = $this->full_bars; if (!$size)                 return -1;
        $iFrom   = 0;
        $iTo     = $size-1; if ($this->full_to_openTime < $time) return -1;
        $barFrom = ['time' => $this->full_from_openTime];
        $barTo   = ['time' => $this->full_to_openTime  ];
        $i       = -1;

        while (true) {                                              // walk through the bar range and recursively reduce the
            if ($barFrom['time'] >= $time) {                        // bar range
                $i = $iFrom;
                break;
            }
            if ($barTo['time']==$time || $size==2) {
                $i = $iTo;
                break;
            }

            $midSize = (int) ceil($size/2);                         // cut remaining range into halves
            $iMid    = $iFrom + $midSize - 1;
            $barMid  = $this->getBar($iMid);

            if ($barMid['time'] <= $time) { $barFrom = $barMid; $iFrom = $iMid; }
            else                          { $barTo   = $barMid; $iTo   = $iMid; }
            $size = $iTo - $iFrom + 1;
        }
        return $i;
    }


    /**
     * Return the offset of the bar covering the specified time.
     *
     * @param  int $time - time
     *
     * @return int - offset or -1 if no such bar exists
     */
    public function findBarOffset($time) {
        Assert::int($time);

        $size = $this->full_bars;
        if (!$size) return -1;

        $offset = $this->findTimeOffset($time);

        if ($offset < 0) {                                          // time is younger than the youngest [openTime]
            $closeTime = $this->full_to_closeTime;
            if ($time < $closeTime) {                               // time is covered by the youngest bar
                return $size-1;
            }
            return -1;
        }

        if ($offset == 0) {
            if ($this->full_from_openTime == $time)                 // time exactly matches the oldest bar
                return 0;
            return -1;                                              // time is older than the oldest bar
        }

        $bar = $this->getBar($offset);
        if ($bar['time'] == $time)                                  // time exactly matches the resolved bar
            return $offset;
        $offset--;

        $bar       = $this->getBar($offset);
        $closeTime = Rost::periodCloseTime($bar['time'], $this->period);

        if ($time < $closeTime)                                     // time is covered by the previous bar
            return $offset;
        return -1;                                                  // time isn't covered by the previous bar meaning there's
    }                                                               // a gap between the previous and the following bar


    /**
     * Return the offset of the bar covering the specified time. If no such bar exists return the offset of the last
     * existing previous bar.
     *
     * @param  int $time - time
     *
     * @return int - offset oder -1 if no such bar exists (i.e. time is older than the oldest bar)
     */
    public function findBarOffsetPrevious($time) {
        Assert::int($time);

        $size = $this->full_bars;
        if (!$size)
            return -1;

        $offset = $this->findTimeOffset($time);
        if ($offset < 0)                                            // time is younger than the youngest bar[openTime]
            return $size-1;

        $bar = $this->getBar($offset);

        if ($bar['time'] == $time)                                  // time exactly matches the resolved bar
            return $offset;
        return $offset - 1;                                         // time is older than the resolved bar
    }


    /**
     * Return the offset of the bar covering the specified time. If no such bar exists return the offset of the next
     * existing bar.
     *
     * @param  int $time - time
     *
     * @return int - offset or -1 if no such bar exists (i.e. time is younger than the youngest bar)
     */
    public function findBarOffsetNext($time) {
        Assert::int($time);

        $size = $this->full_bars;
        if (!$size)
            return -1;

        $offset = $this->findTimeOffset($time);

        if ($offset < 0) {                                          // time is younger than the youngest bar[openTime]
            $closeTime = $this->full_to_closeTime;
            return ($closeTime > $time) ? $size-1 : -1;
        }
        if ($offset == 0)                                           // time is older than or exactly matches the first bar
            return 0;

        $bar = $this->getBar($offset);
        if ($bar['time'] == $time)                                  // time exactly matches bar[openTime]
            return $offset;

        $offset--;                                                  // time is within the previous bar or between the
        $bar = $this->getBar($offset);                              // previous and the findTimeOffset() bar

        $closeTime = Rost::periodCloseTime($bar['time'], $this->period);
        if ($closeTime > $time)                                     // time is within the previous bar
            return $offset;
        return ($offset+1 < $size) ? $offset+1 : -1;                // time is younger than bar[closeTime] which means there
    }                                                               // is a gap between the previous and the following bar


    /**
     * Remove a part of the HistoryFile and adjust its file size. If replacement bars are specified the removed bars are
     * replaced by those bars.
     *
     * @param  int   $offset             - Start offset of the bars to remove with 0 (zero) pointing to the first bar from
     *                                     the beginning (the oldest bar). If offset is negative then removing starts that
     *                                     far from the end (the youngest bar). <br>
     *
     * @param  int   $length  [optional] - Number of bars to remove. If length is omitted everything from offset to the end
     *                                     of the history (the youngest bar) is removed. If length is specified and is
     *                                     positive then that many bars starting from offset will be removed. If length is
     *                                     specified and is negative then all bars starting from offset will be removed
     *                                     except length bars at the end of the history. <br>
     *
     * @param  array $replace [optional] - ROST_PRICE_BAR data. If replacement bars are specified then the removed bars are
     *                                     replaced with these bars. If offset and length are such that nothing is removed
     *                                     then the replacement bars are inserted at the specified offset. If offset is one
     *                                     greater than the greatest existing offset the replacement bars are appended. <br>
     * @return void
     *
     * @example
     * <pre>
     *  HistoryFile::spliceBars(0, 1)       // remove the first bar
     *  HistoryFile::spliceBars(-1)         // remove the last bar
     *  HistoryFile::spliceBars(0, -2)      // remove all except the last two bars
     * </pre>
     */
    public function spliceBars($offset, $length=0, array $replace=[]) {
        Assert::int($offset, '$offset');
        Assert::int($length, '$length');

        // determine absolute start offset: max. value for appending is one position after history end
        if ($offset >= 0) {
            if ($offset > $this->full_bars)   throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
            $fromOffset = $offset;
        }
        else if ($offset < -$this->full_bars) throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
        else $fromOffset = $this->full_bars + $offset;

        // determine absolute end offset
        $argc = func_num_args();
        if ($argc <= 1) {
            $toOffset = $this->full_to_offset;
        }
        else if ($length >= 0) {
            $toOffset = $fromOffset + $length - 1;
            if ($toOffset > $this->full_to_offset) throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }
        else if ($fromOffset == $this->full_bars)  throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        else if ($length < $offset && $offset < 0) throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        else {
            $toOffset = $this->full_to_offset + $length;
            if ($toOffset+1 < $fromOffset)         throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }

        // determine absolute length
        $length = $toOffset - $fromOffset + 1;
        if (!$length) $toOffset = -1;
        if (!$length && !$replace) {                            // nothing to do
            Output::out(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length.'  $bars=0  (nothing to do)');
            return;
        }

        Output::out(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length);
        $this->showMetaData(false, true, false);

        // modify history file
        if      (!$replace) $this->removeBars($fromOffset, $length);
        else if (!$length)  $this->insertBars($fromOffset, $replace);
        else {
            $hstFromBar = $this->getBar($fromOffset);
            $hstToBar   = $this->getBar($toOffset);
            Output::out(__METHOD__.'()  replacing '.$length.' bar(s) from offset '.$fromOffset.' ('.gmdate('d-M-Y H:i:s', $hstFromBar['time']).') to offset '.$toOffset.' ('.gmdate('d-M-Y H:i:s', $hstToBar['time']).') with '.($size=sizeof($replace)).' bars from '.gmdate('d-M-Y H:i:s', $replace[0]['time']).' to '.gmdate('d-M-Y H:i:s', $replace[$size-1]['time']));
            $this->removeBars($fromOffset, $length);
            $this->insertBars($fromOffset, $replace);
        }
    }


    /**
     * Remove a part of the HistoryFile and shorten its file size.
     *
     * @param  int $offset            - Start offset of the bars to remove with 0 (zero) pointing to the first bar from the
     *                                  beginning (the oldest bar). If offset is negative then removing starts that far from
     *                                  the end (the youngest bar). <br>
     *
     * @param  int $length [optional] - Number of bars to remove. If length is omitted everything from offset to the end of
     *                                  the history (the youngest bar) is removed. If length is specified and is positive
     *                                  then that many bars starting from offset will be removed. If length is specified and
     *                                  is negative then all bars starting from offset will be removed except length bars at
     *                                  the end of the history. <br>
     * @return void
     */
    public function removeBars($offset, $length=0) {
        Assert::int($offset, '$offset');
        Assert::int($length, '$length');

        // determine absolute start offset: max. value for appending is one position after history end
        if ($offset >= 0) {
            if ($offset >= $this->full_bars)  throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
            $fromOffset = $offset;
        }
        else if ($offset < -$this->full_bars) throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
        else $fromOffset = $this->full_bars + $offset;

        // determine absolute end offset
        $argc = func_num_args();
        if ($argc <= 1) {
            $toOffset = $this->full_to_offset;
        }
        else if ($length >= 0) {
            $toOffset = $fromOffset + $length - 1;
            if ($toOffset > $this->full_to_offset) throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }
        else if ($length < $offset && $offset < 0) throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        else {
            $toOffset = $this->full_to_offset + $length;
            if ($toOffset+1 < $fromOffset)         throw new InvalidValueException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }

        // determine absolute length
        $length = $toOffset - $fromOffset + 1;
        if (!$length) {                                         // nothing to do
            Output::out(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length.'  (nothing to do)');
            return;
        }

        $hstFromBar = $this->getBar($fromOffset);
        $hstToBar   = $this->getBar($toOffset);
        Output::out(__METHOD__.'()  removing '.$length.' bar(s) from offset '.$fromOffset.' ('.gmdate('d-M-Y H:i:s', $hstFromBar['time']).') to offset '.$toOffset.' ('.gmdate('d-M-Y H:i:s', $hstToBar['time']).')');
    }


    /**
     * Insert bars at the specified offset of the HistoryFile and increase its file size.
     *
     * @param  int $offset - Bar offset to insert bars at with with 0 (zero) pointing to the first bar from the beginning
     *                       (the oldest bar). If offset is negative then the bars are inserted that far from the end
     *                       (the youngest bar). <br>
     *
     * @param  array $bars - bars to insert (ROST_PRICE_BAR[])
     *
     * @return void
     */
    public function insertBars($offset, array $bars) {
        Assert::int($offset, '$offset');

        // determine absolute start offset: max. value for appending is one position after history end
        if ($offset >= 0) {
            if ($offset > $this->full_bars)   throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
            $fromOffset = $offset;
        }
        else if ($offset < -$this->full_bars) throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
        else $fromOffset = $this->full_bars + $offset;

        if (!$bars) {                                            // nothing to do
            Output::out(__METHOD__.'()  $fromOffset='.$fromOffset.'  $bars=0  (nothing to do)');
            return;
        }

        $hstFromBar = $this->getBar($fromOffset);
        Output::out(__METHOD__.'()  inserting '.($size=sizeof($bars)).' bar(s) from '.gmdate('d-M-Y H:i:s', $bars[0]['time']).' to '.gmdate('d-M-Y H:i:s', $bars[$size-1]['time']).' at offset '.$fromOffset.' ('.gmdate('d-M-Y H:i:s', $hstFromBar['time']).')');

        /*
        $array = [0, 1, 2, 3, 4, 5];
        echof($array);
        array_splice($array, 7, 2, [6, 7]);
        echof($array);

        M1::full_bars             = 101381
        M1::full_from_offset      = 0
        M1::full_from_openTime    = Mon, 04-Aug-2003 00:00:00
        M1::full_from_closeTime   = Mon, 04-Aug-2003 00:01:00
        M1::full_to_offset        = 101380
        M1::full_to_openTime      = Mon, 10-Nov-2003 09:40:00
        M1::full_to_closeTime     = Mon, 10-Nov-2003 09:41:00
        M1::full_lastSyncTime     = Fri, 07-Nov-2003 10:40:00

        http://stackoverflow.com/questions/103593/using-php-how-to-insert-text-without-overwriting-to-the-beginning-of-a-text-fil
        */
    }


    /**
     * Replace a part of the HistoryFile with the specified bars and adjust its file size.
     *
     * @param  array $bars              - replacement bars (ROST_PRICE_BAR[])
     *
     * @param  int   $offset            - Start offset of the bars to replace with 0 (zero) pointing to the first bar from
     *                                    the beginning (the oldest bar). If offset is negative then replacing starts that
     *                                    far from the end (the youngest bar). <br>
     *
     * @param  int   $length [optional] - Number of bars to replace. If length is omitted everything from offset to the end
     *                                    of the history (the youngest bar) is replaced. If length is specified and is
     *                                    positive then that many bars starting from offset will be replaced. If length is
     *                                    specified and is negative then all bars starting from offset will be replaced
     *                                    except length bars at the end of the history. <br>
     * @return void
     */
    public function replaceBars(array $bars, $offset, $length = null) {
        throw new UnimplementedFeatureException(__METHOD__.'not yet implemented');
    }


    /**
     * Merge the passed bars into the HistoryFile. Existing bars after the last synchronization time overlapping passed bars
     * are replaced. Existing bars not overlapping passed bars are kept.
     *
     * @param  array $bars - M1 bars, will be converted to the HistoryFile's timeframe (ROST_PRICE_BAR[])
     *
     * @return void
     *
     * @todo   rename to mergeBars...()
     */
    public function synchronize(array $bars) {
        switch ($this->period) {
            case PERIOD_M1:  $this->synchronizeM1 ($bars); break;
            case PERIOD_M5:  $this->synchronizeM5 ($bars); break;
            case PERIOD_M15: $this->synchronizeM15($bars); break;
            case PERIOD_M30: $this->synchronizeM30($bars); break;
            case PERIOD_H1:  $this->synchronizeH1 ($bars); break;
            case PERIOD_H4:  $this->synchronizeH4 ($bars); break;
            case PERIOD_D1:  $this->synchronizeD1 ($bars); break;
            case PERIOD_W1:  $this->synchronizeW1 ($bars); break;
            case PERIOD_MN1: $this->synchronizeMN1($bars); break;
            default:
                throw new RuntimeException('Unsupported timeframe $this->period='.$this->period);
        }
    }


    /**
     * Synchronisiert die M1-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M1
     *
     * @return void
     */
    private function synchronizeM1(array $bars) {
        if ($this->closed) throw new IllegalStateException('Cannot process a closed '.get_class($this));
        if (!$bars) return;

        // Offset der Bar, die den Zeitpunkt abdeckt, ermitteln
        $lastSyncTime = $this->full_lastSyncTime;
        $offset = Rost::findBarOffsetNext($bars, PERIOD_M1, $lastSyncTime);

        // Bars vor Offset verwerfen
        if ($offset == -1) return;                                              // alle Bars liegen vor $lastSyncTime

        $bars = array_slice($bars, $offset);
        $size = sizeof($bars);

        // History-Offsets fuer die verbliebene Bar-Range ermitteln
        $hstOffsetFrom = $this->findBarOffsetNext($bars[0]['time']);
        if ($hstOffsetFrom == -1) {                                             // Zeitpunkt ist juenger als die juengste Bar
            $this->appendBars($bars);
        }
        else {
            // History-Range mit Bar-Range ersetzen
            $hstOffsetTo = $this->findBarOffsetPrevious($bars[$size-1]['time']);
            $length      = $hstOffsetTo - $hstOffsetFrom + 1;
            $this->spliceBars($hstOffsetFrom, $length, $bars);
        }
    }


    /**
     * Synchronisiert die M5-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M5
     *
     * @return void
     */
    private function synchronizeM5(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die M15-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M15
     *
     * @return void
     */
    private function synchronizeM15(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die M30-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M30
     *
     * @return void
     */
    private function synchronizeM30(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die H1-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode H1
     *
     * @return void
     */
    private function synchronizeH1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die H4-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode H4
     *
     * @return void
     */
    private function synchronizeH4(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die D1-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode D1
     *
     * @return void
     */
    private function synchronizeD1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die W1-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode W1
     *
     * @return void
     */
    private function synchronizeW1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die MN1-History dieser Instanz.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode MN1
     *
     * @return void
     */
    private function synchronizeMN1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Fuegt der Historydatei dieser Instanz Bardaten hinzu. Die Daten werden ans Ende der Zeitreihe angefuegt.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M1
     *
     * @return void
     */
    public function appendBars(array $bars) {
        switch ($this->period) {
            case PERIOD_M1:  $this->appendToM1($bars); break;
            case PERIOD_M5:
            case PERIOD_M15:
            case PERIOD_M30:
            case PERIOD_H1:
            case PERIOD_H4:
            case PERIOD_D1:  $this->appendToTimeframe($bars); break;
            case PERIOD_W1:  $this->appendToW1       ($bars); break;
            case PERIOD_MN1: $this->appendToMN1      ($bars); break;
            default:
                throw new RuntimeException('Unsupported timeframe $this->period='.$this->period);
        }
    }


    /**
     * Fuegt der M1-History dieser Instanz weitere Daten hinzu.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M1
     *
     * @return void
     */
    private function appendToM1(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.get_class($this));
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmdate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmdate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $this->barBuffer = array_merge($this->barBuffer, $bars);
        $bufferSize      = sizeof($this->barBuffer);

        if (!$this->full_bars) {                                          // History ist noch leer
            $this->full_from_offset    = 0;
            $this->full_from_openTime  = $this->barBuffer[0]['time'];
            $this->full_from_closeTime = $this->barBuffer[0]['time'] + 1*MINUTE;
        }
        $this->full_bars         = $this->stored_bars + $bufferSize;
        $this->full_to_offset    = $this->full_bars - 1;
        $this->full_to_openTime  = $this->barBuffer[$bufferSize-1]['time'];
        $this->full_to_closeTime = $this->barBuffer[$bufferSize-1]['time'] + 1*MINUTE;

        $this->lastM1DataTime    = $bars[sizeof($bars)-1]['time'];
        $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

        if ($bufferSize > $this->barBufferSize)
            $this->flush($this->barBufferSize);
    }


    /**
     * Fuegt der History dieser Instanz weitere Daten hinzu.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M1
     *
     * @return void
     */
    private function appendToTimeframe(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.get_class($this));
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmdate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmdate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $currentBar = null;
        $bufferSize = sizeof($this->barBuffer);
        if ($bufferSize)
            $currentBar = &$this->barBuffer[$bufferSize-1];

        foreach ($bars as $bar) {
            if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur naechsten M5-Bar erkennen
                // letzte Bar aktualisieren ('time' und 'open' unveraendert)
                if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
                if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                                     $currentBar['close']  = $bar['close'];
                                                                     $currentBar['ticks'] += $bar['ticks'];
            }
            else {
                // neue Bar beginnen
                $openTime           =  $bar['time'] - $bar['time'] % $this->period*MINUTES;
                $this->barBuffer[]  =  $bar;
                $currentBar         = &$this->barBuffer[$bufferSize++];
                $currentBar['time'] =  $openTime;
                $closeTime          =  $openTime + $this->period*MINUTES;

                // Metadaten aktualisieren
                if (!$this->full_bars) {                                          // History ist noch leer
                    $this->full_from_offset    = 0;
                    $this->full_from_openTime  = $openTime;
                    $this->full_from_closeTime = $closeTime;
                }
                $this->full_bars         = $this->stored_bars + $bufferSize;
                $this->full_to_offset    = $this->full_bars - 1;
                $this->full_to_openTime  = $openTime;
                $this->full_to_closeTime = $closeTime;
            }
            $this->lastM1DataTime    = $bar['time'];
            $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

            // ggf. Buffer flushen
            if ($bufferSize > $this->barBufferSize)
                $bufferSize -= $this->flush($this->barBufferSize);
        }
    }


    /**
     * Fuegt der W1-History dieser Instanz weitere Daten hinzu.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M1
     *
     * @return void
     */
    private function appendToW1(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.get_class($this));
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmdate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmdate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $currentBar = null;
        $bufferSize =  sizeof($this->barBuffer);
        if ($bufferSize)
            $currentBar = &$this->barBuffer[$bufferSize-1];

        foreach ($bars as $bar) {
            if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur naechsten W1-Bar erkennen
                // letzte Bar aktualisieren ('time' und 'open' unveraendert)
                if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
                if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                        $currentBar['close']  = $bar['close'];
                                                        $currentBar['ticks'] += $bar['ticks'];
            }
            else {
                // neue Bar beginnen
                $dow                = (int) gmdate('w', $bar['time']);            // 00:00, Montag
                $openTime           =  $bar['time'] - $bar['time']%DAY - (($dow+6)%7)*DAYS;
                $this->barBuffer[]  =  $bar;
                $currentBar         = &$this->barBuffer[$bufferSize++];
                $currentBar['time'] =  $openTime;
                $closeTime          =  $openTime + 1*WEEK;

                // Metadaten aktualisieren
                if (!$this->full_bars) {                                          // History ist noch leer
                    $this->full_from_offset    = 0;
                    $this->full_from_openTime  = $openTime;
                    $this->full_from_closeTime = $closeTime;
                }
                $this->full_bars         = $this->stored_bars + $bufferSize;
                $this->full_to_offset    = $this->full_bars - 1;
                $this->full_to_openTime  = $openTime;
                $this->full_to_closeTime = $closeTime;
            }
            $this->lastM1DataTime    = $bar['time'];
            $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

            // ggf. Buffer flushen
            if ($bufferSize > $this->barBufferSize) {
                $bufferSize -= $this->flush($this->barBufferSize);
            }
        }
    }


    /**
     * Fuegt der MN1-History dieser Instanz weitere Daten hinzu.
     *
     * @param  array $bars - ROST_PRICE_BAR-Daten der Periode M1
     *
     * @return void
     */
    private function appendToMN1(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.get_class($this));
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmdate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmdate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $currentBar = null;
        $bufferSize =  sizeof($this->barBuffer);
        if ($bufferSize)
            $currentBar = &$this->barBuffer[$bufferSize-1];

        foreach ($bars as $bar) {
            if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur naechsten MN1-Bar erkennen
                // letzte Bar aktualisieren ('time' und 'open' unveraendert)
                if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
                if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                                     $currentBar['close']  = $bar['close'];
                                                                     $currentBar['ticks'] += $bar['ticks'];
            }
            else {
                // neue Bar beginnen
                $dom = (int) gmdate('d', $bar['time']);
                $m   = (int) gmdate('m', $bar['time']);
                $y   = (int) gmdate('Y', $bar['time']);                           // 00:00, 1. des Monats
                $openTime           =  $bar['time'] - $bar['time']%DAYS - ($dom-1)*DAYS;
                $this->barBuffer[]  =  $bar;
                $currentBar         = &$this->barBuffer[$bufferSize++];
                $currentBar['time'] =  $openTime;
                $closeTime          =  gmmktime(0, 0, 0, $m+1, 1, $y);            // 00:00, 1. des naechsten Monats

                // Metadaten aktualisieren
                if (!$this->full_bars) {                                          // History ist noch leer
                    $this->full_from_offset    = 0;
                    $this->full_from_openTime  = $openTime;
                    $this->full_from_closeTime = $closeTime;
                }
                $this->full_bars         = $this->stored_bars + $bufferSize;
                $this->full_to_offset    = $this->full_bars - 1;
                $this->full_to_openTime  = $openTime;
                $this->full_to_closeTime = $closeTime;
            }
            $this->lastM1DataTime    = $bar['time'];
            $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

            // ggf. Buffer flushen
            if ($bufferSize > $this->barBufferSize)
                $bufferSize -= $this->flush($this->barBufferSize);
        }
    }


    /**
     * Schreibt eine Anzahl ROST_PRICE_BARs aus dem Barbuffer in die History-Datei.
     *
     * @param  int $count - Anzahl zu schreibender Bars (default: alle Bars)
     *
     * @return int - Anzahl der geschriebenen und aus dem Buffer geloeschten Bars
     */
    public function flush($count = PHP_INT_MAX) {
        if ($this->closed) throw new IllegalStateException('Cannot process a closed '.get_class($this));
        Assert::int($count);
        if ($count < 0)    throw new InvalidValueException('Invalid parameter $count: '.$count);

        $bufferSize = sizeof($this->barBuffer);
        $todo       = min($bufferSize, $count);
        if (!$todo) return 0;


        // (1) FilePointer setzen
        fseek($this->hFile, HistoryHeader::SIZE + ($this->stored_to_offset+1)*$this->barSize);


        // (2) Bars schreiben
        $i = 0;
        foreach ($this->barBuffer as $i => $bar) {
            $T = $bar['time' ];
            $O = $bar['open' ]/$this->pointsPerUnit;
            $H = $bar['high' ]/$this->pointsPerUnit;
            $L = $bar['low'  ]/$this->pointsPerUnit;
            $C = $bar['close']/$this->pointsPerUnit;
            $V = $bar['ticks'];

            MT4::writeHistoryBar400($this->hFile, $this->getDigits(), $T, $O, $H, $L, $C, $V);
            if ($i+1 == $todo)
                break;
        }
        //if ($this->period==PERIOD_M1) echof(__METHOD__.'()  wrote '.$todo.' bars, lastBar.time='.gmdate('D, d-M-Y H:i:s', $this->barBuffer[$todo-1]['time']));


        // (3) Metadaten aktualisieren
        if (!$this->stored_bars) {                                           // Datei war vorher leer
            $this->stored_from_offset    = 0;
            $this->stored_from_openTime  = $this->barBuffer[0]['time'];
            $this->stored_from_closeTime = Rost::periodCloseTime($this->stored_from_openTime, $this->period);
        }
        $this->stored_bars         = $this->stored_bars + $todo;
        $this->stored_to_offset    = $this->stored_bars - 1;
        $this->stored_to_openTime  = $this->barBuffer[$todo-1]['time'];
        $this->stored_to_closeTime = Rost::periodCloseTime($this->stored_to_openTime, $this->period);

        // lastSyncTime je nachdem setzen, ob noch weitere Daten im Buffer sind
        $this->stored_lastSyncTime = ($todo < $bufferSize) ? $this->stored_to_closeTime : $this->lastM1DataTime + 1*MINUTE;

        //$this->full* aendert sich nicht


        // (4) HistoryHeader aktualisieren
        $this->hstHeader->setLastSyncTime($this->stored_lastSyncTime);
        $this->writeHistoryHeader();


        // (5) Barbuffer um die geschriebenen Bars kuerzen
        if ($todo == $bufferSize) $this->barBuffer = [];
        else                      $this->barBuffer = array_slice($this->barBuffer, $todo);

        return $todo;
    }


    /**
     * Schreibt den HistoryHeader in die Datei.
     *
     * @return int - Anzahl der geschriebenen Bytes
     */
    private function writeHistoryHeader() {
        fseek($this->hFile, 0);
        $format  = HistoryHeader::packFormat();
        $written = fwrite($this->hFile, pack($format, $this->hstHeader->getFormat(),            // V
                                                      $this->hstHeader->getCopyright(),         // a64
                                                      $this->hstHeader->getSymbol(),            // a12
                                                      $this->hstHeader->getPeriod(),            // V
                                                      $this->hstHeader->getDigits(),            // V
                                                      $this->hstHeader->getSyncMarker(),        // V
                                                      $this->hstHeader->getLastSyncTime()));    // V
        return $written;                                                                        // x52
    }


    /**
     * Nur zum Debuggen
     *
     * @return void
     */
    public function showMetaData($showStored=true, $showFull=true, $showFile=true) {
        $Pxx = periodDescription($this->period);

        ($showStored || $showFull || $showFile) && echof(NL);
        if ($showStored) {
            echof($Pxx.'::stored_bars           = '. $this->stored_bars);
            echof($Pxx.'::stored_from_offset    = '. $this->stored_from_offset);
            echof($Pxx.'::stored_from_openTime  = '.($this->stored_from_openTime  ? gmdate('D, d-M-Y H:i:s', $this->stored_from_openTime ) : 0));
            echof($Pxx.'::stored_from_closeTime = '.($this->stored_from_closeTime ? gmdate('D, d-M-Y H:i:s', $this->stored_from_closeTime) : 0));
            echof($Pxx.'::stored_to_offset      = '. $this->stored_to_offset);
            echof($Pxx.'::stored_to_openTime    = '.($this->stored_to_openTime    ? gmdate('D, d-M-Y H:i:s', $this->stored_to_openTime   ) : 0));
            echof($Pxx.'::stored_to_closeTime   = '.($this->stored_to_closeTime   ? gmdate('D, d-M-Y H:i:s', $this->stored_to_closeTime  ) : 0));
            echof($Pxx.'::stored_lastSyncTime   = '.($this->stored_lastSyncTime   ? gmdate('D, d-M-Y H:i:s', $this->stored_lastSyncTime  ) : 0));
        }
        if ($showFull) {
            $showStored && echof(NL);
            echof($Pxx.'::full_bars             = '. $this->full_bars);
            echof($Pxx.'::full_from_offset      = '. $this->full_from_offset);
            echof($Pxx.'::full_from_openTime    = '.($this->full_from_openTime    ? gmdate('D, d-M-Y H:i:s', $this->full_from_openTime   ) : 0));
            echof($Pxx.'::full_from_closeTime   = '.($this->full_from_closeTime   ? gmdate('D, d-M-Y H:i:s', $this->full_from_closeTime  ) : 0));
            echof($Pxx.'::full_to_offset        = '. $this->full_to_offset);
            echof($Pxx.'::full_to_openTime      = '.($this->full_to_openTime      ? gmdate('D, d-M-Y H:i:s', $this->full_to_openTime     ) : 0));
            echof($Pxx.'::full_to_closeTime     = '.($this->full_to_closeTime     ? gmdate('D, d-M-Y H:i:s', $this->full_to_closeTime    ) : 0));
            echof($Pxx.'::full_lastSyncTime     = '.($this->full_lastSyncTime     ? gmdate('D, d-M-Y H:i:s', $this->full_lastSyncTime    ) : 0));
        }
        if ($showFile) {
            ($showStored || $showFull) && echof(NL);
            echof($Pxx.'::lastM1DataTime        = '.($this->lastM1DataTime        ? gmdate('D, d-M-Y H:i:s', $this->lastM1DataTime       ) : 0));
            echof($Pxx.'::fp                    = '.($fp=ftell($this->hFile)).' (bar offset '.(($fp-HistoryHeader::SIZE)/$this->barSize).')');
        }
    }
}
