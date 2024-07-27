<?php
namespace rosasurfer\rt\lib\dukascopy;

use rosasurfer\ministruts\core\CObject;
use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\di\proxy\Output;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;
use rosasurfer\ministruts\file\FileSystem as FS;
use rosasurfer\ministruts\log\Logger;

use rosasurfer\rt\lib\LZMA;
use rosasurfer\rt\lib\dukascopy\HttpClient as DukascopyClient;
use rosasurfer\rt\model\DukascopySymbol;

use function rosasurfer\ministruts\first;
use function rosasurfer\ministruts\isLittleEndian;
use function rosasurfer\ministruts\last;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\fxTimezoneOffset;
use function rosasurfer\rt\periodDescription;
use function rosasurfer\rt\periodToStr;
use function rosasurfer\rt\priceTypeDescription;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\HOURS;
use const rosasurfer\ministruts\L_WARN;
use const rosasurfer\ministruts\MINUTE;
use const rosasurfer\ministruts\MINUTES;

use const rosasurfer\rt\DUKASCOPY_BAR_SIZE;
use const rosasurfer\rt\DUKASCOPY_TICK_SIZE;
use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PRICE_BID;
use const rosasurfer\rt\PRICE_ASK;
use const rosasurfer\rt\PRICE_MEDIAN;


/**
 * Dukascopy
 *
 * Functionality for downloading and processing Dukascopy history data.
 */
class Dukascopy extends CObject {


    /** @var HttpClient */
    protected $httpClient;

    /** @var array[] - internal cache for single fetched history start data */
    protected $historyStarts;

    /** @var array[] - internal cache for all fetched history start data */
    protected $allHistoryStarts;

    /** @var array[] */
    protected $history;


    /**
     * Return a Dukascopy specific HTTP client. The instance is kept to enable "keep-alive" connections.
     *
     * @return HttpClient
     */
    protected function getHttpClient() {
        if (!$this->httpClient) {
            $this->httpClient = new DukascopyClient();
        }
        return $this->httpClient;
    }


    /**
     * Decompress a compressed Dukascopy data file and return its content.
     *
     * @param  string $compressedFile    - name of the compressed data file
     * @param  string $saveAs [optional] - if specified the decompressed file is stored in a file with the given name
     *                                     (default: no additional storage)
     *
     * @return string - decompressed file content
     */
    protected function decompressFile($compressedFile, $saveAs = null) {
        Assert::string($compressedFile, '$compressedFile');
        return $this->decompressData(file_get_contents($compressedFile), $saveAs);
    }


    /**
     * Decompress a compressed Dukascopy data string and return it.
     *
     * @param  string $data              - compressed string with bars or ticks
     * @param  string $saveAs [optional] - if specified the decompressed data is stored in a file with the given name
     *                                     (default: no additional storage)
     *
     * @return string - decompressed data
     */
    public function decompressData($data, $saveAs = null) {
        Assert::string($data, '$data');
        Assert::nullOrString($saveAs, '$saveAs');
        if (isset($saveAs) && !strlen($saveAs)) throw new InvalidValueException('Invalid parameter $saveAs: ""');

        $rawData = LZMA::decompressData($data);

        if (isset($saveAs)) {
            FS::mkDir(dirname($saveAs));
            $tmpFile = tempnam(dirname($saveAs), basename($saveAs));
            file_put_contents($tmpFile, $rawData);              // make sure an existing file can't be corrupt
            if (is_file($saveAs)) unlink($saveAs);
            rename($tmpFile, $saveAs);
        }
        return $rawData;
    }


    /**
     * Get history for the specified symbol, bar period and time. The range of the returned data depends on the requested
     * bar period.
     *
     * @param  DukascopySymbol $symbol               - symbol to get history data for
     * @param  int             $period               - bar period identifier: PERIOD_M1 | PERIOD_D1
     * @param  int             $time                 - FXT time
     * @param  bool            $optimized [optional] - returned bar format (see notes)
     *
     * @return array - An empty array if history for the specified bar period and time is not available. Otherwise a
     *                 timeseries array with each element describing a single price bar as follows:
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
    public function getHistory(DukascopySymbol $symbol, $period, $time, $optimized = false) {
        Assert::int($period, '$period');
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        Assert::int($time, '$time');
        $nameU = strtoupper($symbol->getName());
        $day   = $time - $time%DAY;                                                         // 00:00 FXT

        if (!isset($this->history[$nameU][$period][$day][PRICE_MEDIAN])) {
            // load Bid and Ask, calculate Median and store everything in the cache
            if (!$bids = $this->loadHistory($symbol, $period, $day, PRICE_BID)) return [];  // raw
            if (!$asks = $this->loadHistory($symbol, $period, $day, PRICE_ASK)) return [];  // raw
            $median = $this->calculateMedian($bids, $asks);                                 // optimized
            $this->history[$nameU][$period][$day][PRICE_MEDIAN] = $median;
        }

        // limit memory consumption
        $purges = array_diff(array_keys($this->history), [$nameU]);
        foreach ($purges as $stale) unset($this->history[$stale]);                          // drop old symbols
        $purges = array_diff(array_keys($this->history[$nameU][$period]), [$day-DAY, $day, $day+DAY]);
        foreach ($purges as $stale) unset($this->history[$nameU][$period][$stale]);         // drop old timeseries

        if ($optimized)
            return $this->history[$nameU][$period][$day][PRICE_MEDIAN];

        if (!isset($this->history[$nameU][$period][$day]['real'])) {
            $real = $this->calculateReal($symbol, $this->history[$nameU][$period][$day][PRICE_MEDIAN]);
            $this->history[$nameU][$period][$day]['real'] = $real;
        }
        return $this->history[$nameU][$period][$day]['real'];
    }


    /**
     * Load history data from Dukascopy for the specified bar period and time.
     *
     * @param  DukascopySymbol $symbol
     * @param  int             $period - bar period identifier: PERIOD_M1 | PERIOD_D1
     * @param  int             $time   - FXT time
     * @param  int             $type   - price type identifier: PRICE_BID | PRICE_ASK
     *
     * @return array - An empty array if history for the specified bar period and time is not available. Otherwise a
     *                 timeseries array with each element describing a single price bar as follows:
     * <pre>
     * Array(
     *     'time'       => (int),       // bar open time in FXT
     *     'time_delta' => (int),       // bar offset to 00:00 FXT in seconds
     *     'open'       => (int),       // open value in point
     *     'high'       => (int),       // high value in point
     *     'low'        => (int),       // low value in point
     *     'close'      => (int),       // close value in point
     *     'volume'     => (float),     // volume
     * )
     * </pre>
     */
    protected function loadHistory(DukascopySymbol $symbol, $period, $time, $type) {
        Assert::int($period, '$period');
        if ($period != PERIOD_M1)                     throw new InvalidValueException('Invalid parameter $period: '.periodToStr($period));
        Assert::int($time, '$time');
        Assert::int($type, '$type');
        if (!in_array($type, [PRICE_BID, PRICE_ASK])) throw new InvalidValueException('Invalid parameter $type: '.$type);

        // Day transition time (Midnight) for Dukascopy data is at 00:00 GMT (~02:00 FXT). Each FXT day requires Dukascopy
        // data of the current and the previous GMT day. If data is present in the internal cache the method doesn't connect
        // to Dukascopy. Otherwise data is downloaded, converted to FXT and cached.
        //         +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        // GMT:    |   Sun   ||   Mon   |   Tue   |   Wed   |   Thu   |   Fri   ||   Sat   |   Sun   ||   Mon   |
        //         +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        //      +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        // FXT: |   Sun   ||   Mon   |   Tue   |   Wed   |   Thu   |   Fri   ||   Sat   |   Sun   ||   Mon   |
        //      +---------++---------+---------+---------+---------+---------++---------+---------++---------+

        $name  = $symbol->getName();
        $nameU = strtoupper($name);
        $day          = $time - $time%DAY;                                          // 00:00 FXT
        $previousDay  = $day - 1*DAY;                                               // 00:00 FXT
        $currentDayOk = $previousDayOk = false;

        if (isset($this->history[$nameU][$period][$day][$type])) {
            if (sizeof($this->history[$nameU][$period][$day][$type]) == PERIOD_D1)  // that's if data of the day is complete
                return $this->history[$nameU][$period][$day][$type];

            $first = first($this->history[$nameU][$period][$day][$type]);           // if cached data starts with 00:00, the
            $previousDayOk = ($first['time_delta'] === 0);                          // previous GMT day was already loaded

            $last = last($this->history[$nameU][$period][$day][$type]);             // if cached data ends with 23:59, the
            $currentDayOk = ($last['time_delta'] === 23*HOURS+59*MINUTES);          // current GMT day was already loaded
        }

        // download and convert missing data
        foreach ([$previousDay=>$previousDayOk, $day=>$currentDayOk] as $day => $dayOk) {
            if (!$dayOk) {
                $data = $this->getHttpClient()->downloadHistory($name, $day, $type);// here 00:00 FXT can be used as GMT
                if (!$data) return [];                                              // that's if data is not available
                $data = $this->decompressData($data);
                $this->parseBarData($data, $symbol, $day, $period, $type);          // here 00:00 FXT can be used as GMT
            }
        }
        // now downloaded data is complete and stored on FXT boundaries
        return $this->history[$nameU][$period][$day][$type];
    }


    /**
     * Parse a string with binary history data for the specified time and bar period, convert GMT times to FXT, split on FXT
     * boundaries and store the resulting timeseries in the internal cache.
     *
     * @param  string          $data   - binary history data
     * @param  DukascopySymbol $symbol - symbol the data belongs to
     * @param  int             $day    - GMT timestamp of the history
     * @param  int             $period - bar period identifier: PERIOD_M1 | PERIOD_D1
     * @param  int             $type   - price type identifier: PRICE_BID | PRICE_ASK
     *
     * <pre>
     * Bar format stored in the cache:
     * -------------------------------
     * Array(
     *     'time'       => (int),       // bar open time in FXT
     *     'time_delta' => (int),       // bar offset to 00:00 FXT in seconds
     *     'open'       => (int),       // open value in point
     *     'high'       => (int),       // high value in point
     *     'low'        => (int),       // low value in point
     *     'close'      => (int),       // close value in point
     *     'volume'     => (float)      // volume
     * )
     * </pre>
     */
    protected function parseBarData($data, DukascopySymbol $symbol, $day, $period, $type) {
        Assert::string($data, '$data');
        Assert::int($day, '$day');
        Assert::int($period, '$period');
        if ($period != PERIOD_M1)                     throw new InvalidValueException('Invalid parameter $period: '.periodToStr($period));
        Assert::int($type, '$type');
        if (!in_array($type, [PRICE_BID, PRICE_ASK])) throw new InvalidValueException('Invalid parameter $type: '.$type);

        $symbolU = strtoupper($symbol->getName());
        $day -= $day%DAY;                                           // 00:00 GMT

        // read bars
        $bars = $this->readBarData($data, $symbol, $type, $day);
        if (sizeof($bars) != PERIOD_D1) throw new RuntimeException('Unexpected number of Dukascopy bars in '.periodDescription($period).' data: '
                                                                   .sizeof($bars).' ('.(sizeof($bars) > PERIOD_D1 ? 'more':'less').' then a day)');

        // Day transition time (Midnight) for Dukascopy data is at 00:00 GMT (~02:00 FXT).
        //         +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        // GMT:    |   Sun   ||   Mon   |   Tue   |   Wed   |   Thu   |   Fri   ||   Sat   |   Sun   ||   Mon   |
        //         +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        //      +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        // FXT: |   Sun   ||   Mon   |   Tue   |   Wed   |   Thu   |   Fri   ||   Sat   |   Sun   ||   Mon   |
        //      +---------++---------+---------+---------+---------+---------++---------+---------++---------+

        // drop GMT and add FXT data
        $prev = $next = null;
        $fxtOffset = fxTimezoneOffset($day, $prev, $next);
        foreach ($bars as &$bar) {
            $timeGMT = $day + $bar['timeDelta'];
            if ($timeGMT >= $next['time'])                          // If $day = "Sun, 00:00 GMT" bars may cover a DST transition.
                $fxtOffset = $next['offset'];                       // In this case $fxtOffset must be updated during the loop.
            $bar['time'      ] = $timeGMT + $fxtOffset;             // FXT
            $bar['time_delta'] = $bar['time'] % DAY;                // offset to 00:00 FXT
            unset($bar['timeDelta']);
        }; unset($bar);

        // resolve array offset of 00:00 FXT
        $newDayOffset = PERIOD_D1 - $fxtOffset/MINUTES;
        if ($fxtOffset == $next['offset']) {                        // additional lot check if bars cover a DST transition
            $firstBar = $bars[$newDayOffset];
            $lastBar  = $bars[$newDayOffset-1];
            if ($lastBar['volume']) {
                Output::out('[Warn]    '.gmdate('D, d-M-Y', $day).'   volume mis-match during DST change.');
                Output::out('Day of DST change ('.gmdate('D, d-M-Y', $lastBar['time']).') ended with:');
                Output::out($bars[$newDayOffset-1]);
                Output::out('Day after DST change ('.gmdate('D, d-M-Y', $firstBar['time']).') started with:');
                Output::out($bars[$newDayOffset]);
            }
        }

        // store bars in cache
        $bars1 = array_slice($bars, 0, $newDayOffset);
        $bars2 = array_slice($bars, $newDayOffset);
        $day1 = $bars1[0]['time'] - $bars1[0]['time_delta'];
        $day2 = $bars2[0]['time'] - $bars2[0]['time_delta'];

        if (isset($this->history[$symbolU][$period][$day1][$type])) {
            // make sure bars to merge match
            $cache =& $this->history[$symbolU][$period][$day1][$type];
            $lastBarTime = last($cache)['time'];
            $nextBarTime = $bars1[0]['time'];
            if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: '.priceTypeDescription($type).', $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
            $cache = array_merge($cache, $bars1);
        }
        else {
            $this->history[$symbolU][$period][$day1][$type] = $bars1;
        }

        if (isset($this->history[$symbolU][$period][$day2][$type])) {
            // make sure bars to merge match
            $cache =& $this->history[$symbolU][$period][$day2][$type];
            $lastBarTime = last($bars2)['time'];
            $nextBarTime = $cache[0]['time'];
            if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: '.priceTypeDescription($type).', $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
            $cache = array_merge($bars2, $cache);
        }
        else {
            $this->history[$symbolU][$period][$day2][$type] = $bars2;
        }
    }


    /**
     * Derive a PRICE_MEDIAN timeseries array from the specified Bid and Ask history.
     *
     * @param  array $bids - Bid bars
     * @param  array $asks - Ask bars
     *
     * @return array[] - a timeseries array with each element describing a single price bar as follows:
     *
     * <pre>
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
    protected function calculateMedian(array $bids, array $asks) {
        if (sizeof($bids) != PERIOD_D1) throw new InvalidValueException('Invalid size of parameter $bids: '.($size=sizeof($bids)).' ('.($size > PERIOD_D1 ? 'more':'less').' then a day)');
        if (sizeof($asks) != PERIOD_D1) throw new InvalidValueException('Invalid size of parameter $asks: '.($size=sizeof($asks)).' ('.($size > PERIOD_D1 ? 'more':'less').' then a day)');
        $medians = [];

        foreach ($bids as $i => $bid) {
            $ask = $asks[$i];
            $median = [];
            $median['time' ] = $bid['time'];
            $median['open' ] = (int) round(($bid['open' ] + $ask['open' ])/2);
            $median['high' ] = (int) round(($bid['high' ] + $ask['high' ])/2);
            $median['low'  ] = (int) round(($bid['low'  ] + $ask['low'  ])/2);
            $median['close'] = (int) round(($bid['close'] + $ask['close'])/2);

            // Bid and Ask bar have been validated before. Validate the calculated Median bar
            // to fix price spikes with negative spread by adjusting High and Low.
            if ($bid['open'] > $ask['open'] || $bid['high'] > $ask['high'] || $bid['low'] > $ask['low'] || $bid['close'] > $ask['close']) {
                $median['high'] = max($median['open'], $median['high'], $median['low'], $median['close']);
                $median['low' ] = min($median['open'], $median['high'], $median['low'], $median['close']);
            }

            // Calculation of synthetic ticks as number of steps to touch all inside bar paths. Goal is a minimum overall
            // tick value to speed-up tests. On PERIOD_M1 accuracy of this algorythm is more than sufficient.
            $ticks = ($median['high'] - $median['low']) << 1;                                               // unchanged bar (O == C)
            if      ($median['open'] < $median['close']) $ticks += ($median['open' ] - $median['close']);   // bull bar
            else if ($median['open'] > $median['close']) $ticks += ($median['close'] - $median['open' ]);   // bear bar
            $median['ticks'] = $ticks ?: 1;                                                                 // min. tick value of 1

            $medians[$i] = $median;
        }
        return $medians;
    }


    /**
     * Calculate a real timeseries of the given optimized timeseries.
     *
     * @param  DukascopySymbol $symbol  - symbol the timeseries belongs to
     * @param  array           $history - optimized timeseries
     *
     * @return array[] - a timeseries array with each element describing a single price bar as follows:
     *
     * <pre>
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (float),          // open value in real terms
     *     'high'  => (float),          // high value in real terms
     *     'low'   => (float),          // low value in real terms
     *     'close' => (float),          // close value in real terms
     *     'ticks' => (int),            // volume (if available) or number of synthetic ticks
     * )
     * </pre>
     */
    protected function calculateReal(DukascopySymbol $symbol, array $history) {
        $point = $symbol->getPointValue();
        $results = [];

        foreach ($history as $bar) {
            $new = [];
            $new['time' ] = $bar['time'];
            $new['open' ] = $bar['open' ] * $point;
            $new['high' ] = $bar['high' ] * $point;
            $new['low'  ] = $bar['low'  ] * $point;
            $new['close'] = $bar['close'] * $point;
            $new['ticks'] = $bar['ticks'];
            $results[] = $new;
        }
        return $results;
    }


    /**
     * Parse a file with Dukascopy bar data and convert it to a data array.
     *
     * @param  string          $fileName - name of file with Dukascopy bar data
     * @param  DukascopySymbol $symbol   - symbol the data belongs to
     * @param  int             $type     - price type (for error messages as data may contain errors)
     * @param  int             $time     - time of the bar data (for error messages as data may contain errors)
     *
     * @return array[] - a PHP representation of struct DUKASCOPY_BAR[], single elements are defined as follows:
     *
     * <pre>
     * Array(
     *     'timeDelta' => (int),        // time difference in seconds since 00:00 GMT
     *     'open'      => (int),        // open value in point
     *     'close'     => (int),        // close value in point
     *     'low'       => (int),        // low value in point
     *     'high'      => (int),        // high value in point
     *     'volume'    => (float)       // volume
     * )
     * </pre>
     */
    protected function readBarFile($fileName, DukascopySymbol $symbol, $type, $time) {
        Assert::string($fileName, '$fileName');
        return $this->readBarData(file_get_contents($fileName), $symbol, $type, $time);
    }


    /**
     * Parse a string with Dukascopy bar data and convert it to a timeseries array.
     *
     * @param  string          $data   - string with Dukascopy bar data
     * @param  DukascopySymbol $symbol - symbol the data belongs to
     * @param  int             $type   - price type (for error messages as data may contain errors)
     * @param  int             $time   - time of the history (for error messages as data may contain errors)
     *
     * @return array[] - a PHP representation of struct DUKASCOPY_BAR[], single elements are defined as follows:
     *
     * <pre>
     * Array(
     *     'timeDelta' => (int),        // time difference in seconds since 00:00 GMT
     *     'open'      => (int),        // open value in point
     *     'close'     => (int),        // close value in point
     *     'low'       => (int),        // low value in point
     *     'high'      => (int),        // high value in point
     *     'volume'    => (float)       // volume
     * )
     * </pre>
     */
    public function readBarData($data, DukascopySymbol $symbol, $type, $time) {
        Assert::string($data, '$data');
        $lenData = strlen($data);
        if (!$lenData || $lenData%DUKASCOPY_BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' '.priceTypeDescription($type).' data: '.$lenData.' (not an even DUKASCOPY_BAR_SIZE)');

        static $isLittleEndian; !isset($isLittleEndian) && $isLittleEndian=isLittleEndian();
        $point = $symbol->getPointValue();

        $bars = [];
        $offset = 0;
        $i = -1;
        while ($offset < $lenData) {
            $i++;
            $bar = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh", $data);
            $s   = substr($data, $offset+20, 4);
            $vol = unpack('f', $isLittleEndian ? strrev($s) : $s);  // unpack() doesn't support explicit big-endian floats,
            $bar['volume'] = round($vol[1], 2);                     // on little-endian machines the byte order of field
            $offset += DUKASCOPY_BAR_SIZE;                          // "vol" must be reversed manually

            // validate bar data
            if ($bar['open' ] > $bar['high'] || $bar['open' ] < $bar['low' ] || $bar['close'] > $bar['high'] || $bar['close'] < $bar['low' ]) {
                $O = $bar['open' ] * $point;
                $H = $bar['high' ] * $point;
                $L = $bar['low'  ] * $point;
                $C = $bar['close'] * $point;
                Logger::log('Illegal '.$symbol->getName().' '.priceTypeDescription($type)." data for bar[$i] of ".gmdate('D, d-M-Y H:i:s', $time).": O=$O H=$H L=$L C=$C, adjusting high/low...", L_WARN);

                $bar['high'] = max($bar['open'], $bar['high'], $bar['low'], $bar['close']);
                $bar['low' ] = min($bar['open'], $bar['high'], $bar['low'], $bar['close']);
            }
            $bars[] = $bar;
        }
        return $bars;
    }


    /**
     * Parse a file with Dukascopy tick data and convert it to a data array.
     *
     * @param  string $fileName - name of file with Dukascopy tick data
     *
     * @return array - DUKASCOPY_TICK[] data
     */
    public static function readTickFile($fileName) {
        Assert::string($fileName, '$fileName');
        return static::readTickData(file_get_contents($fileName));
    }


    /**
     * Parse a string with Dukascopy tick data and convert it to a data array.
     *
     * @param  string $data - string with Dukascopy tick data
     *
     * @return array - DUKASCOPY_TICK[] data
     */
    public static function readTickData($data) {
        Assert::string($data);

        $lenData = strlen($data); if (!$lenData || $lenData%DUKASCOPY_TICK_SIZE) throw new RuntimeException('Odd length of passed data: '.$lenData.' (not an even DUKASCOPY_TICK_SIZE)');
        $offset  = 0;
        $ticks   = [];
        $i       = -1;

        static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();

        // unpack doesn't support explicit big-endian floats, on little-endian machines the byte order
        // of fields "bidSize" and "askSize" has to be reversed manually
        while ($offset < $lenData) {
            $i++;
            $ticks[] = unpack("@$offset/NtimeDelta/Nask/Nbid", $data);
            $s1      = substr($data, $offset+12, 4);
            $s2      = substr($data, $offset+16, 4);
            $size    = unpack('fask/fbid', $isLittleEndian ? strrev($s1).strrev($s2) : $s1.$s2);    // manually reverse
            $ticks[$i]['askSize'] = round($size['ask'], 2);                                         // on little-endian machines
            $ticks[$i]['bidSize'] = round($size['bid'], 2);
            $offset += DUKASCOPY_TICK_SIZE;
        }
        return $ticks;
    }


    /**
     * Fetch history start for the specified symbol.
     *
     * @param  DukascopySymbol $symbol
     *
     * @return array - history start times per timeframe or an empty value in case of errors
     *
     * <pre>
     * Array(
     *     [{period-id}] => [{timestamp}],          // e.g.: PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *     [{period-id}] => [{timestamp}],          //       PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *     [{period-id}] => [{timestamp}],          //       PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *     ...                                      //       PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     * )
     * </pre>
     */
    public function fetchHistoryStart(DukascopySymbol $symbol) {
        $name  = $symbol->getName();
        $nameU = strtoupper($name);

        if (isset($this->allHistoryStarts[$nameU]))
            return $this->allHistoryStarts[$nameU];
        if (isset($this->historyStarts[$nameU]))
            return $this->historyStarts[$nameU];

        Output::out('[Info]    '.str_pad($name, 6).'  downloading history start times from Dukascopy...');

        $data = $this->getHttpClient()->downloadHistoryStart($name);
        if (strlen($data))
            return $this->historyStarts[$nameU] = $this->readHistoryStartSection($data);
        return [];
    }


    /**
     * Fetch history start times from Dukascopy for all available symbols. Returns a list of arrays with history start times
     * for each available symbol.
     *
     * @return array[] - list of arrays in a format as follows:
     *
     * <pre>
     * Array(
     *     [{symbol}] => [
     *         [{period-id}] => [{timestamp}],      // e.g.: PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *         [{period-id}] => [{timestamp}],      //       PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *         [{period-id}] => [{timestamp}],      //       PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *         [{period-id}] => [{timestamp}],      //       PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     *     ],
     *     [{symbol}] => [
     *         ...
     *     ],
     *     ...
     * )
     * </pre>
     */
    public function fetchHistoryStarts() {
        if ($this->allHistoryStarts)
            return $this->allHistoryStarts;

        Output::out('[Info]    Downloading history start times from Dukascopy...');

        $data = $this->getHttpClient()->downloadHistoryStart();
        if (strlen($data))
            return $this->allHistoryStarts = $this->readHistoryStarts($data);
        return [];
    }


    /**
     * Parse a string with history start records of multiple symbols.
     *
     * @param  string $data - binary data
     *
     * @return array[] - associative list of arrays with variable number of elements each describing a symbol's history start
     *                   of a single timeframe as follows:
     * <pre>
     * Array(
     *     [{symbol}] => [
     *         [{period-id}] => [{timestamp}],          // e.g.: PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *         [{period-id}] => [{timestamp}],          //       PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *         [{period-id}] => [{timestamp}],          //       PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *         [{period-id}] => [{timestamp}],          //       PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     *     ],
     *     [{symbol}] => [
     *         ...
     *     ],
     *     ...
     * )
     * </pre>
     */
    protected function readHistoryStarts($data) {
        Assert::string($data);
        $lenData = strlen($data);
        if (!$lenData) throw new InvalidValueException('Illegal length of history start data: '.$lenData);

        $symbols = [];
        $start   = $length = $symbol = $high = $count = null;
        $offset  = 0;

        while ($offset < $lenData) {
            extract(unpack("@$offset/Cstart/Clength", $data));
            if ($start)                     throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.$offset.': start='.$start);
            $offset += 2;
            extract(unpack("@$offset/A${length}symbol/Nhigh/Ncount", $data));
            if (strlen($symbol) != $length) throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.$offset.': symbol="'.$symbol.'"  length='.$length);
            if ($high)                      throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.($offset+$length).': highInt='.$high);
            if ($count != 4)                throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.($offset+$length+1).': count='.$count);
            $offset += $length + 8;

            $timeframes = $this->readHistoryStartSection($data, $offset, $count);
            if ($timeframes) {                                                  // skip symbols without history
                ksort($timeframes);
                $symbols[strtoupper($symbol)] = $timeframes;
            }
            $offset += $count*16;
        }
        ksort($symbols);
        return $symbols;
    }


    /**
     * Parse a binary string with a history start section (consecutive history start records).
     *
     * @param  string $data              - binary data
     * @param  int    $offset [optional] - string offset to start     (default: 0)
     * @param  int    $count  [optional] - number of records to parse (default: until the end of the string)
     *
     * @return array - array with variable number of elements each describing history start of a single timeframe
     *                 as follows:
     * <pre>
     * Array(
     *     [{period-id}] => [{timestamp}],              // e.g.: PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *     [{period-id}] => [{timestamp}],              //       PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *     [{period-id}] => [{timestamp}],              //       PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *     ...                                          //       PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     * )
     * </pre>
     */
    protected function readHistoryStartSection($data, $offset = 0, $count = null) {
        $lenData = strlen($data);
        if (!is_int($offset) || $offset < 0)    throw new InvalidValueException('Invalid parameter $offset: '.$offset.' ('.gettype($offset).')');
        if ($offset >= $lenData)                throw new InvalidValueException('Invalid parameters, mis-matching $offset/$lenData: '.$offset.'/'.$lenData);
        if (!isset($count)) $count = PHP_INT_MAX;
        elseif (!is_int($count) || $count <= 0) throw new InvalidValueException('Invalid parameter $count: '.$count.' ('.gettype($count).')');

        $timeframes = [];

        while ($offset < $lenData && $count) {
            $timeframes += $this->readHistoryStartRecord($data, $offset);
            $offset += 16;
            $count--;
        }
        ksort($timeframes);
        return $timeframes;
    }


    /**
     * Parse a DUKASCOPY_TIMEFRAME_START record at the given offset of a binary string.
     *
     * @param  string $data   - binary data
     * @param  int    $offset - offset
     *
     * @return array - a key-value pair [{period-id} => {timestamp}] or an empty array if history of the given timeframe
     *                 is not available
     */
    protected function readHistoryStartRecord($data, $offset) {
        // check platform
        if (PHP_INT_SIZE == 8) {
            // 64-bit integers and format codes are supported
            $record = unpack("@$offset/J2", $data);
            if ($record[1] == -1)                                               // uint64_max: sometimes used as tickdata identifier
                $record[1] = 0;
            $timeframe = $record[1] / 1000 / MINUTES;
            if (!is_int($timeframe) || (string)$timeframe==periodToStr($timeframe)) throw new RuntimeException('Unexpected Dukascopy timeframe identifier: '.$record[1]);
            $record[1] = $timeframe;
            if ($record[2] < 0) throw new \RangeException('Invalid Java timestamp: '.sprintf('%u', $record[2]).' (out of range)');
            if ($record[2] == PHP_INT_MAX)
                return [];                                                      // int64_max: no history available
            if ($record[2] % 1000) $record[2] = round($record[2]/1000, 3);
            else                   $record[2] = (int)($record[2]/1000);
        }
        else {
            // 32-bit integers: 64-bit format codes are not supported
            $ints = unpack("@$offset/N4", $data);
            $record = [];
            foreach ($ints as $i => $int) {
                $int = sprintf('%u', $int);
                if ($i % 2) $record[($i+1)/2] = bcmul($int, '4294967296', 0);   // 2 ^ 32
                else        $record[ $i=$i/2] = bcadd($record[$i], $int, 0);
            }
            if ($record[1] == '18446744073709551615')                           // uint64_max: sometimes used as tickdata identifier
                $record[1] = '0';
            /** @var int $timeframe */
            $timeframe = ((int) bcdiv($record[1], '1000', 0)) / MINUTES;
            if (!is_int($timeframe) || (string)$timeframe==periodToStr($timeframe)) throw new RuntimeException('Unexpected Dukascopy timeframe identifier: '.$record[1]);
            $record[1] = $timeframe;
            if ($record[2] == '9223372036854775807')                            // int64_max: no history available
                return [];
            if (!bcmod($record[2], '1000')) $record[2] =   (int) bcdiv($record[2], '1000', 0);
            else                            $record[2] = (float) bcdiv($record[2], '1000', 3);
        }

        if ($record[1] == PERIOD_D1) $record[2] -= ($record[2] % DAY);
        else                         $record[2]  = fxTime($record[2]);

        return [$record[1] => $record[2]];
    }
}
