<?php
namespace rosasurfer\xtrade\metatrader;

use rosasurfer\core\Object;
use rosasurfer\debug\ErrorHandler;
use rosasurfer\exception\FileNotFoundException;
use rosasurfer\exception\IllegalStateException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\xtrade\XTrade;

use const rosasurfer\xtrade\PERIOD_D1;
use const rosasurfer\xtrade\PERIOD_H1;
use const rosasurfer\xtrade\PERIOD_H4;
use const rosasurfer\xtrade\PERIOD_M1;
use const rosasurfer\xtrade\PERIOD_M15;
use const rosasurfer\xtrade\PERIOD_M30;
use const rosasurfer\xtrade\PERIOD_M5;
use const rosasurfer\xtrade\PERIOD_MN1;
use const rosasurfer\xtrade\PERIOD_W1;


/**
 * Object wrapping a single MT4 history file ("*.hst").
 */
class HistoryFile extends Object {


    /** @var int - handle of an open history file */
    protected $hFile;

    /** @var string - simple history file name (basename + extension) */
    protected $fileName;

    /** @var string - server name */
    protected $serverName;

    /** @var string - full server directory name */
    protected $serverDirectory;

    /** @var bool - whether or not the history file is closed and all instance resources are released */
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

    /** @var array[] - internal write buffer (XTRADE_PRICE_BAR[]) */
    protected $barBuffer = [];

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
     * Whether or not the history file is closed and all instance resources are released.
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
     * @param  array $args
     */
    public function __construct(...$args) {
        $argc = sizeof($args);
        if      ($argc == 1) $this->__construct1(...$args);
        else if ($argc == 5) $this->__construct2(...$args);
        else throw new InvalidArgumentException('Invalid number of arguments: '.$argc);
    }


    /**
     * Overloaded constructor.
     *
     * Create a new instance from an existing MT4 history file. Existing data is kept.
     *
     * @param  string $fileName - MT4 history file name
     */
    private function __construct1($fileName) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
        if (!is_file($fileName))   throw new FileNotFoundException('Invalid parameter $fileName: "'.$fileName.'" (file not found)');

        // resolve directory, file and server name
        $realName              = realPath($fileName);
        $this->fileName        = baseName($realName);
        $this->serverDirectory = dirname ($realName);
        $this->serverName      = baseName($this->serverDirectory);

        // validate the file size
        $fileSize = fileSize($fileName);
        if ($fileSize < HistoryHeader::SIZE) throw new MetaTraderException('filesize.insufficient: Invalid or unsupported format of "'.$fileName.'": fileSize='.$fileSize.' (minFileSize='.HistoryHeader::SIZE.')');

        // open file and read/validate the header
        $this->hFile     = fOpen($fileName, 'r+b');               // FILE_READ|FILE_WRITE
        $this->hstHeader = new HistoryHeader(fRead($this->hFile, HistoryHeader::SIZE));

        if (!strCompareI($this->fileName, $this->getSymbol().$this->getTimeframe().'.hst')) throw new MetaTraderException('filename.mis-match: File name/symbol mis-match of "'.$fileName.'": header="'.$this->getSymbol().','.XTrade::periodDescription($this->getTimeframe()).'"');
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
     */
    private function __construct2($symbol, $timeframe, $digits, $format, $serverDirectory) {
        if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
        if (!strLen($symbol))                         throw new InvalidArgumentException('Invalid parameter $symbol: ""');
        if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
        if (!is_int($timeframe))                      throw new IllegalTypeException('Illegal type of parameter $timeframe: '.getType($timeframe));
        if (!MT4::isStdTimeframe($timeframe))         throw new InvalidArgumentException('Invalid parameter $timeframe: '.$timeframe.' (not a MetaTrader standard timeframe)');
        if (!is_string($serverDirectory))             throw new IllegalTypeException('Illegal type of parameter $serverDirectory: '.getType($serverDirectory));
        if (!is_dir($serverDirectory))                throw new InvalidArgumentException('Directory "'.$serverDirectory.'" not found');

        $this->hstHeader       = new HistoryHeader($format, null, $symbol, $timeframe, $digits, null, null);
        $this->serverDirectory = realPath($serverDirectory);
        $this->serverName      = baseName($this->serverDirectory);
        $this->fileName        = $symbol.$timeframe.'.hst';

        // rewrite history file and header
        mkDirWritable($this->serverDirectory);
        $fileName    = $this->serverDirectory.'/'.$this->fileName;
        $this->hFile = fOpen($fileName, 'wb');                      // FILE_WRITE
        $this->writeHistoryHeader();

        // read and initialize the file's metadata
        $this->initMetaData();
    }


    /**
     * Read the file's metadata and initialize local vars. Called only by a constructor.
     */
    private function initMetaData() {
        $this->period          = $this->hstHeader->getPeriod();
        $this->barSize         = $this->getVersion()==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        $this->barPackFormat   = MT4::BAR_getPackFormat($this->getVersion());
        $this->barUnpackFormat = MT4::BAR_getUnpackFormat($this->getVersion());

        $this->pointsPerUnit = pow(10, $this->getDigits());
        $this->pointSize     = 1/$this->pointsPerUnit;

        $fileSize = fileSize($this->serverDirectory.'/'.$this->fileName);
        if ($fileSize > HistoryHeader::SIZE) {
            $bars    = ($fileSize-HistoryHeader::SIZE) / $this->barSize;
            fFlush($this->hFile);
            $barFrom = $barTo = unpack($this->barUnpackFormat, fRead($this->hFile, $this->barSize));
            if ($bars > 1) {
                fSeek($this->hFile, HistoryHeader::SIZE + ($bars-1)*$this->barSize);
                $barTo = unpack($this->barUnpackFormat, fRead($this->hFile, $this->barSize));
            }
            $from_offset    = 0;
            $from_openTime  = $barFrom['time'];
            $from_closeTime = XTrade::periodCloseTime($from_openTime, $this->period);

            $to_offset      = $bars-1;
            $to_openTime    = $barTo['time'];
            $to_closeTime   = XTrade::periodCloseTime($to_openTime, $this->period);

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
        catch (\Exception $ex) {
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
            $hTmp=$this->hFile; $this->hFile=null;
            fClose($hTmp);
        }
        return $this->closed=true;
    }


    /**
     * Set the size of the write buffer.
     *
     * @param  int $size - buffer size
     */
    public function setBarBufferSize($size) {
        if ($this->closed)  throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!is_int($size)) throw new IllegalTypeException('Illegal type of parameter $size: '.getType($size));
        if ($size < 0)      throw new InvalidArgumentException('Invalid parameter $size: '.$size);

        $this->barBufferSize = $size;
    }


    /**
     * Return the bar at the specified bar offset.
     *
     * @param  int $offset
     *
     * @return array|null - XTRADE_PRICE_BAR if the bar was not yet stored and is returned from the write buffer
     *                      HISTORY_BAR      if the bar was stored and is returned from the history file
     *                      NULL             if no such bar exists (offset is larger than the file's number of bars)
     *
     * @see  HistoryFile::getXTradeBar()
     * @see  HistoryFile::getHistoryBar()
     */
    public function getBar($offset) {
        if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));
        if ($offset < 0)      throw new InvalidArgumentException('Invalid parameter $offset: '.$offset);

        if ($offset >= $this->full_bars)                                        // bar[$offset] does not exist
            return null;

        if ($offset > $this->stored_to_offset)                                  // bar[$offset] is a buffered bar (XTRADE_PRICE_BAR)
            return $this->barBuffer[$offset-$this->stored_to_offset-1];

        fFlush($this->hFile);
        fSeek($this->hFile, HistoryHeader::SIZE + $offset*$this->barSize);      // bar[$offset] is a stored bar (HISTORY_BAR)
        return unpack($this->barUnpackFormat, fRead($this->hFile, $this->barSize));
    }


    /**
     * Return the bar offset of a time. This is the bar position a bar with the specified open time would be inserted.
     *
     * @param  int $time - time
     *
     * @return int - Offset or -1 if $time is younger than the youngest bar. To write a bar at offset -1 the history file
     *               has to be expanded.
     */
    public function findTimeOffset($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

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
     * Gibt den Offset der Bar dieser Historydatei zurueck, die den angegebenen Zeitpunkt exakt abdeckt.
     *
     * @param  int $time - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert
     */
    public function findBarOffset($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        $size = sizeOf($this->full_bars);
        if (!$size)
            return -1;

        $offset = $this->findTimeOffset($time);

        if ($offset < 0) {                                              // Zeitpunkt liegt nach der juengsten bar[openTime]
            $closeTime = $this->full_to_closeTime;
            if ($time < $closeTime)                                     // Zeitpunkt liegt innerhalb der juengsten Bar
                return $size-1;
            return -1;
        }

        if ($offset == 0) {
            if ($this->full_from_openTime == $time)                     // Zeitpunkt liegt exakt auf der aeltesten Bar
                return 0;
            return -1;                                                  // Zeitpunkt ist aelter die aelteste Bar
        }

        $bar = $this->getBar($offset);
        if ($bar['time'] == $time)                                      // Zeitpunkt liegt exakt auf der jeweiligen Bar
            return $offset;
        $offset--;

        $bar       = $this->getBar($offset);
        $closeTime = XTrade::periodCloseTime($bar['time'], $this->period);

        if ($time < $closeTime)                                         // Zeitpunkt liegt in der vorhergehenden Bar
            return $offset;
        return -1;                                                      // Zeitpunkt liegt nicht in der vorhergehenden Bar,
    }                                                                   // also Luecke zwischen der vorhergehenden und der
                                                                        // folgenden Bar

    /**
     * Gibt den Offset der Bar dieser Historydatei zurueck, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar,
     * wird der Offset der letzten vorhergehenden Bar zurueckgegeben.
     *
     * @param  int $time - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist aelter als die aelteste Bar)
     */
    public function findBarOffsetPrevious($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        $size = $this->full_bars;
        if (!$size)
            return -1;

        $offset = $this->findTimeOffset($time);
        if ($offset < 0)                                                           // Zeitpunkt liegt nach der juengsten bar[openTime]
            return $size-1;

        $bar = $this->getBar($offset);

        if ($bar['time'] == $time)                                                 // Zeitpunkt liegt exakt auf der jeweiligen Bar
            return $offset;
        return $offset - 1;                                                        // Zeitpunkt ist aelter als die Bar desselben Offsets
    }


    /**
     * Gibt den Offset der Bar dieser Historydatei zurueck, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar,
     * wird der Offset der naechstfolgenden Bar zurueckgegeben.
     *
     * @param  int $time - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist juenger als das Ende der juengsten Bar)
     */
    public function findBarOffsetNext($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        $size = $this->full_bars;
        if (!$size)
            return -1;

        $offset = $this->findTimeOffset($time);

        if ($offset < 0) {                                              // Zeitpunkt liegt nach der juengsten bar[openTime]
            $closeTime = $this->full_to_closeTime;
            return ($closeTime > $time) ? $size-1 : -1;
        }
        if ($offset == 0)                                               // Zeitpunkt liegt vor oder exakt auf der ersten Bar
            return 0;

        $bar = $this->getBar($offset);
        if ($bar['time'] == $time)                                      // Zeitpunkt stimmt mit bar[openTime] ueberein
            return $offset;

        $offset--;                                                      // Zeitpunkt liegt in der vorherigen oder zwischen der
        $bar = $this->getBar($offset);                                  // vorherigen und der TimeOffset-Bar

        $closeTime = XTrade::periodCloseTime($bar['time'], $this->period);
        if ($closeTime > $time)                                         // Zeitpunkt liegt innerhalb dieser vorherigen Bar
            return $offset;
        return ($offset+1 < $size) ? $offset+1 : -1;                    // Zeitpunkt liegt nach bar[closeTime], also Luecke...
    }                                                                   // zwischen der vorherigen und der folgenden Bar


    /**
     * Entfernt einen Teil der Historydatei und ersetzt ihn mit den uebergebenen Bardaten. Die Groesse der Datei wird
     * entsprechend angepasst.
     *
     * @param  int   $offset - If offset is zero or positive then the start of the removed bars is at that bar offset from
     *                         the beginning of the history. If offset is negative then removing starts that far from the end
     *                         of the history.
     *
     * @param  int   $length - If length is omitted everything from offset to the end of the history is removed. If length is
     *                         specified and is positive then that many bars will be removed. If length is specified and is
     *                         negative then length bars at the end of the history  will be left.
     *
     * @param  array $bars   - XTRADE_PRICE_BAR data. If replacement bars are specified then the removed bars are replaced with
     *                         bars from this array. If offset and length are such that nothing is removed then the bars from
     *                         the replacement array are inserted at the specified offset. If offset is one greater than the
     *                         greatest existing offset the replacement array is appended.
     *
     * Examples: - HistoryFile->spliceBars(0, 1)  removes the first bar
     *           - HistoryFile->spliceBars(-1)    removes the last bar (to be exact: everything from the last bar to the end)
     *           - HistoryFile->spliceBars(0, -2) removes everything from the beginning to the end except the last two bars
     */
    public function spliceBars($offset, $length=0, array $bars=[]) {
        if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));
        if (!is_int($length)) throw new IllegalTypeException('Illegal type of parameter $length: '.getType($length));

        // absoluten Startoffset ermitteln: fuer appendBars() gueltiger Wert bis zu ein Element hinterm History-Ende
        if ($offset >= 0) {
            if ($offset > $this->full_bars)    throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
            $fromOffset = $offset;
        }
        else if ($offset < -$this->full_bars) throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
        else $fromOffset = $this->full_bars + $offset;

        // absoluten Endoffset ermitteln
        $argc = func_num_args();
        if ($argc <= 1) {
            $toOffset = $this->full_to_offset;
        }
        else if ($length >= 0) {
            $toOffset = $fromOffset + $length - 1;
            if ($toOffset > $this->full_to_offset)  throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }
        else if ($fromOffset == $this->full_bars)  throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        else if ($length < $offset && $offset < 0) throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        else {
            $toOffset = $this->full_to_offset + $length;
            if ($toOffset+1 < $fromOffset)          throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }

        // absolute Laenge ermitteln
        $length = $toOffset - $fromOffset + 1;
        if (!$length) $toOffset = -1;
        if (!$length && !$bars) {                                         // nothing to do
            echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length.'  $bars=0  (nothing to do)');
            return;
        }

        echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length);
        $this->showMetaData(false, true, false);


        // History bearbeiten
        if      (!$bars)   $this->removeBars($fromOffset, $length);
        else if (!$length) $this->insertBars($fromOffset, $bars);
        else {
            $hstFromBar = $this->getBar($fromOffset);
            $hstToBar   = $this->getBar($toOffset);
            echoPre(__METHOD__.'()  replacing '.$length.' bar(s) from offset '.$fromOffset.' ('.gmDate('d-M-Y H:i:s', $hstFromBar['time']).') to offset '.$toOffset.' ('.gmDate('d-M-Y H:i:s', $hstToBar['time']).') with '.($size=sizeOf($bars)).' bars from '.gmDate('d-M-Y H:i:s', $bars[0]['time']).' to '.gmDate('d-M-Y H:i:s', $bars[$size-1]['time']));
            $this->removeBars($fromOffset, $length);
            $this->insertBars($fromOffset, $bars);
        }
    }


    /**
     * Entfernt einen Teil der Historydatei. Die Groesse der Datei wird entsprechend gekuerzt.
     *
     * @param  int $offset - If offset is zero or positive then the start of the removed bars is at that bar offset from the beginning
     *                       of the history. If offset is negative then removing starts that far from the end of the history.
     *
     * @param  int $length - If length is omitted everything from offset to the end of the history is removed. If length is specified
     *                       and is positive then that many bars will be removed. If length is specified and is negative then length bars
     *                       at the end of the history  will be left.
     */
    public function removeBars($offset, $length=0) {
        if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));
        if (!is_int($length)) throw new IllegalTypeException('Illegal type of parameter $length: '.getType($length));

        // absoluten Startoffset ermitteln
        if ($offset >= 0) {
            if ($offset >= $this->full_bars)   throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
            $fromOffset = $offset;
        }
        else if ($offset < -$this->full_bars) throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
        else $fromOffset = $this->full_bars + $offset;

        // Endoffset ermitteln
        $argc = func_num_args();
        if ($argc <= 1) {
            $toOffset = $this->full_to_offset;
        }
        else if ($length >= 0) {
            $toOffset = $fromOffset + $length - 1;
            if ($toOffset > $this->full_to_offset)  throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }
        else if ($length < $offset && $offset < 0) throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        else {
            $toOffset = $this->full_to_offset + $length;
            if ($toOffset+1 < $fromOffset)          throw new InvalidArgumentException('Invalid parameter $length='.$length.' at $offset='.$offset.' ('.$this->full_bars.' bars in history)');
        }

        // absolute Laenge ermitteln
        $length = $toOffset - $fromOffset + 1;
        if (!$length) {                                         // nothing to do
            echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $toOffset='.$toOffset.'  $length='.$length.'  (nothing to do)');
            return;
        }

        $hstFromBar = $this->getBar($fromOffset);
        $hstToBar   = $this->getBar($toOffset);
        echoPre(__METHOD__.'()  removing '.$length.' bar(s) from offset '.$fromOffset.' ('.gmDate('d-M-Y H:i:s', $hstFromBar['time']).') to offset '.$toOffset.' ('.gmDate('d-M-Y H:i:s', $hstToBar['time']).')');
    }


    /**
     * Fuegt Bardaten am angebenen Offset einer Historydatei ein. Die Datei wird entsprechend vergroessert.
     *
     * @param  int   $offset - If offset is zero or positive then the insertion point is at that bar offset from the
     *                         beginning of the history. If offset is negative then the insertion point is that far from the
     *                         end of the history.
     *
     * @param  array $bars   - einzufuegende XTRADE_PRICE_BAR[]-Daten
     */
    public function insertBars($offset, array $bars) {
        if (!is_int($offset)) throw new IllegalTypeException('Illegal type of parameter $offset: '.getType($offset));

        // absoluten Offset ermitteln
        if ($offset >= 0) {
            if ($offset > $this->full_bars)   throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
            $fromOffset = $offset;
        }
        else if ($offset < -$this->full_bars) throw new InvalidArgumentException('Invalid parameter $offset: '.$offset.' ('.$this->full_bars.' bars in history)');
        else $fromOffset = $this->full_bars + $offset;

        if (!$bars) {                                            // nothing to do
            echoPre(__METHOD__.'()  $fromOffset='.$fromOffset.'  $bars=0  (nothing to do)');
            return;
        }

        $hstFromBar = $this->getBar($fromOffset);
        echoPre(__METHOD__.'()  inserting '.($size=sizeOf($bars)).' bar(s) from '.gmDate('d-M-Y H:i:s', $bars[0]['time']).' to '.gmDate('d-M-Y H:i:s', $bars[$size-1]['time']).' at offset '.$fromOffset.' ('.gmDate('d-M-Y H:i:s', $hstFromBar['time']).')');

        /*
        $array = [0, 1, 2, 3, 4, 5];
        echoPre($array);
        array_splice($array, 7, 2, [6, 7]);
        echoPre($array);

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
     * Ersetzt einen Teil der Historydatei durch andere Bardaten. Die Groesse der Datei wird entsprechend angepasst.
     *
     * @param  int   $offset - If offset is zero or positive then the start of the removed bars is at that bar offset from
     *                         the beginning of the history. If offset is negative then removing starts that far from the
     *                         end of the history.
     *
     * @param  int   $length - If length is omitted everything from offset to the end of the history is removed. If length
     *                         is specified and is positive then that many bars will be removed. If length is specified and
     *                         is negative then the end of the removed part will be that many bars from the end of the
     *                         history.
     *
     * @param  array $bars   - die ersetzenden XTRADE_PRICE_BAR-Daten
     */
    public function replaceBars($offset, $length=null, array $bars) {
    }


    /**
     * Synchronisiert die Historydatei dieser Instanz mit den uebergebenen Daten. Vorhandene Bars, die nach dem letzten
     * Synchronisationszeitpunkt der Datei hinzugefuegt wurden und sich mit den uebergebenen Daten ueberschneiden, werden
     * ersetzt. Vorhandene Bars, die sich mit den uebergebenen Daten nicht ueberschneiden, bleiben unveraendert.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1 (werden automatisch in die Periode der Historydatei konvertiert)
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
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1
     */
    private function synchronizeM1(array $bars) {
        if ($this->closed) throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return false;

        // Offset der Bar, die den Zeitpunkt abdeckt, ermitteln
        $lastSyncTime = $this->full_lastSyncTime;
        $offset       = XTrade::findBarOffsetNext($bars, PERIOD_M1, $lastSyncTime);

        // Bars vor Offset verwerfen
        if ($offset == -1)                                                      // alle Bars liegen vor $lastSyncTime
            return;
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
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M5
     */
    private function synchronizeM5(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die M15-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M15
     */
    private function synchronizeM15(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die M30-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M30
     */
    private function synchronizeM30(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die H1-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode H1
     */
    private function synchronizeH1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die H4-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode H4
     */
    private function synchronizeH4(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die D1-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode D1
     */
    private function synchronizeD1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die W1-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode W1
     */
    private function synchronizeW1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Synchronisiert die MN1-History dieser Instanz.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode MN1
     */
    private function synchronizeMN1(array $bars) {
        throw new UnimplementedFeatureException(__METHOD__.'() not yet implemented');
    }


    /**
     * Fuegt der Historydatei dieser Instanz Bardaten hinzu. Die Daten werden ans Ende der Zeitreihe angefuegt.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1
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
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1
     */
    private function appendToM1(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $this->barBuffer = array_merge($this->barBuffer, $bars);
        $bufferSize      = sizeOf($this->barBuffer);

        if (!$this->full_bars) {                                          // History ist noch leer
            $this->full_from_offset    = 0;
            $this->full_from_openTime  = $this->barBuffer[0]['time'];
            $this->full_from_closeTime = $this->barBuffer[0]['time'] + 1*MINUTE;
        }
        $this->full_bars         = $this->stored_bars + $bufferSize;
        $this->full_to_offset    = $this->full_bars - 1;
        $this->full_to_openTime  = $this->barBuffer[$bufferSize-1]['time'];
        $this->full_to_closeTime = $this->barBuffer[$bufferSize-1]['time'] + 1*MINUTE;

        $this->lastM1DataTime    = $bars[sizeOf($bars)-1]['time'];
        $this->full_lastSyncTime = $this->lastM1DataTime + 1*MINUTE;

        if ($bufferSize > $this->barBufferSize)
            $this->flush($this->barBufferSize);
    }


    /**
     * Fuegt der History dieser Instanz weitere Daten hinzu.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1
     */
    private function appendToTimeframe(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $currentBar = null;
        $bufferSize = sizeOf($this->barBuffer);
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
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1
     */
    private function appendToW1(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $currentBar = null;
        $bufferSize =  sizeOf($this->barBuffer);
        if ($bufferSize)
            $currentBar = &$this->barBuffer[$bufferSize-1];

        foreach ($bars as $i => $bar) {
            if ($bar['time'] < $this->full_to_closeTime) {                       // Wechsel zur naechsten W1-Bar erkennen
                // letzte Bar aktualisieren ('time' und 'open' unveraendert)
                if ($bar['high'] > $currentBar['high']) $currentBar['high' ]  = $bar['high' ];
                if ($bar['low' ] < $currentBar['low' ]) $currentBar['low'  ]  = $bar['low'  ];
                                                                     $currentBar['close']  = $bar['close'];
                                                                     $currentBar['ticks'] += $bar['ticks'];
            }
            else {
                // neue Bar beginnen
                $dow                = (int) gmDate('w', $bar['time']);            // 00:00, Montag
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
            if ($bufferSize > $this->barBufferSize)
                $bufferSize -= $this->flush($this->barBufferSize);
        }
    }


    /**
     * Fuegt der MN1-History dieser Instanz weitere Daten hinzu.
     *
     * @param  array $bars - XTRADE_PRICE_BAR-Daten der Periode M1
     */
    private function appendToMN1(array $bars) {
        if ($this->closed)                             throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!$bars) return;
        if ($bars[0]['time'] <= $this->lastM1DataTime) throw new IllegalStateException('Cannot append bar(s) of '.gmDate('D, d-M-Y H:i:s', $bars[0]['time']).' to history ending at '.gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime));

        $currentBar = null;
        $bufferSize =  sizeOf($this->barBuffer);
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
                $dom = (int) gmDate('d', $bar['time']);
                $m   = (int) gmDate('m', $bar['time']);
                $y   = (int) gmDate('Y', $bar['time']);                           // 00:00, 1. des Monats
                $openTime           =  $bar['time'] - $bar['time']%DAYS - ($dom-1)*DAYS;
                $this->barBuffer[]  =  $bar;
                $currentBar         = &$this->barBuffer[$bufferSize++];
                $currentBar['time'] =  $openTime;
                $closeTime          =  gmMkTime(0, 0, 0, $m+1, 1, $y);            // 00:00, 1. des naechsten Monats

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
     * Schreibt eine Anzahl XTRADE_PRICE_BARs aus dem Barbuffer in die History-Datei.
     *
     * @param  int $count - Anzahl zu schreibender Bars (default: alle Bars)
     *
     * @return int - Anzahl der geschriebenen und aus dem Buffer geloeschten Bars
     */
    public function flush($count=PHP_INT_MAX) {
        if ($this->closed)   throw new IllegalStateException('Cannot process a closed '.__CLASS__);
        if (!is_int($count)) throw new IllegalTypeException('Illegal type of parameter $count: '.getType($count));
        if ($count < 0)      throw new InvalidArgumentException('Invalid parameter $count: '.$count);

        $bufferSize = sizeOf($this->barBuffer);
        $todo       = min($bufferSize, $count);
        if (!$todo) return 0;


        // (1) FilePointer setzen
        fSeek($this->hFile, HistoryHeader::SIZE + ($this->stored_to_offset+1)*$this->barSize);


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
        //if ($this->period==PERIOD_M1) echoPre(__METHOD__.'()  wrote '.$todo.' bars, lastBar.time='.gmDate('D, d-M-Y H:i:s', $this->barBuffer[$todo-1]['time']));


        // (3) Metadaten aktualisieren
        if (!$this->stored_bars) {                                           // Datei war vorher leer
            $this->stored_from_offset    = 0;
            $this->stored_from_openTime  = $this->barBuffer[0]['time'];
            $this->stored_from_closeTime = XTrade::periodCloseTime($this->stored_from_openTime, $this->period);
        }
        $this->stored_bars         = $this->stored_bars + $todo;
        $this->stored_to_offset    = $this->stored_bars - 1;
        $this->stored_to_openTime  = $this->barBuffer[$todo-1]['time'];
        $this->stored_to_closeTime = XTrade::periodCloseTime($this->stored_to_openTime, $this->period);

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
        fSeek($this->hFile, 0);
        $format  = HistoryHeader::packFormat();
        $written = fWrite($this->hFile, pack($format, $this->hstHeader->getFormat(),           // V
                                                                     $this->hstHeader->getCopyright(),        // a64
                                                                     $this->hstHeader->getSymbol(),           // a12
                                                                     $this->hstHeader->getPeriod(),           // V
                                                                     $this->hstHeader->getDigits(),           // V
                                                                     $this->hstHeader->getSyncMarker(),       // V
                                                                     $this->hstHeader->getLastSyncTime()));   // V
                                                                                                                            // x52
        //if ($this->period==PERIOD_M1 && $this->hstHeader->getLastSyncTime()) $this->showMetaData();
        return $written;
    }


    /**
     * Nur zum Debuggen
     */
    public function showMetaData($showStored=true, $showFull=true, $showFile=true) {
        $Pxx = XTrade::periodDescription($this->period);

        ($showStored || $showFull || $showFile) && echoPre(NL);
        if ($showStored) {
            echoPre($Pxx.'::stored_bars           = '. $this->stored_bars);
            echoPre($Pxx.'::stored_from_offset    = '. $this->stored_from_offset);
            echoPre($Pxx.'::stored_from_openTime  = '.($this->stored_from_openTime  ? gmDate('D, d-M-Y H:i:s', $this->stored_from_openTime ) : 0));
            echoPre($Pxx.'::stored_from_closeTime = '.($this->stored_from_closeTime ? gmDate('D, d-M-Y H:i:s', $this->stored_from_closeTime) : 0));
            echoPre($Pxx.'::stored_to_offset      = '. $this->stored_to_offset);
            echoPre($Pxx.'::stored_to_openTime    = '.($this->stored_to_openTime    ? gmDate('D, d-M-Y H:i:s', $this->stored_to_openTime   ) : 0));
            echoPre($Pxx.'::stored_to_closeTime   = '.($this->stored_to_closeTime   ? gmDate('D, d-M-Y H:i:s', $this->stored_to_closeTime  ) : 0));
            echoPre($Pxx.'::stored_lastSyncTime   = '.($this->stored_lastSyncTime   ? gmDate('D, d-M-Y H:i:s', $this->stored_lastSyncTime  ) : 0));
        }
        if ($showFull) {
            $showStored && echoPre(NL);
            echoPre($Pxx.'::full_bars             = '. $this->full_bars);
            echoPre($Pxx.'::full_from_offset      = '. $this->full_from_offset);
            echoPre($Pxx.'::full_from_openTime    = '.($this->full_from_openTime    ? gmDate('D, d-M-Y H:i:s', $this->full_from_openTime   ) : 0));
            echoPre($Pxx.'::full_from_closeTime   = '.($this->full_from_closeTime   ? gmDate('D, d-M-Y H:i:s', $this->full_from_closeTime  ) : 0));
            echoPre($Pxx.'::full_to_offset        = '. $this->full_to_offset);
            echoPre($Pxx.'::full_to_openTime      = '.($this->full_to_openTime      ? gmDate('D, d-M-Y H:i:s', $this->full_to_openTime     ) : 0));
            echoPre($Pxx.'::full_to_closeTime     = '.($this->full_to_closeTime     ? gmDate('D, d-M-Y H:i:s', $this->full_to_closeTime    ) : 0));
            echoPre($Pxx.'::full_lastSyncTime     = '.($this->full_lastSyncTime     ? gmDate('D, d-M-Y H:i:s', $this->full_lastSyncTime    ) : 0));
        }
        if ($showFile) {
            ($showStored || $showFull) && echoPre(NL);
            echoPre($Pxx.'::lastM1DataTime        = '.($this->lastM1DataTime        ? gmDate('D, d-M-Y H:i:s', $this->lastM1DataTime       ) : 0));
            echoPre($Pxx.'::fp                    = '.($fp=fTell($this->hFile)).' (bar offset '.(($fp-HistoryHeader::SIZE)/$this->barSize).')');
        }
    }
}
