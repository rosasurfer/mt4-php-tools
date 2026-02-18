<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\dukascopy;

use RangeException;

use rosasurfer\ministruts\core\CObject;
use rosasurfer\ministruts\core\proxy\Output;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\core\exception\RuntimeException;
use rosasurfer\ministruts\core\exception\UnimplementedFeatureException;
use rosasurfer\ministruts\file\FileSystem as FS;
use rosasurfer\ministruts\log\Logger;

use rosasurfer\rt\RT;
use rosasurfer\rt\lib\LZMA;
use rosasurfer\rt\lib\dukascopy\HttpClient as DukascopyClient;
use rosasurfer\rt\model\DukascopySymbol;

use function rosasurfer\ministruts\first;
use function rosasurfer\ministruts\isLittleEndian;
use function rosasurfer\ministruts\last;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\fxtOffset;
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
 * Functionality for processing Dukascopy history data.
 *
 * @phpstan-import-type DUKASCOPY_BAR_RAW  from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type DUKASCOPY_BAR      from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type DUKASCOPY_TICK_RAW from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type RT_POINT_BAR       from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type RT_PRICE_BAR       from \rosasurfer\rt\phpstan\CustomTypes
 */
class Dukascopy extends CObject
{
    /** @var int - size of the C++ struct DUKASCOPY_BAR in byte */
    const DUKASCOPY_BAR_size = 12;

    /** @var int - size of the C++ struct DUKASCOPY_TIMEFRAME in byte */
    const DUKASCOPY_TIMEFRAME_size = 16;

    /** @var int - size of the C++ struct DUKASCOPY_TICK in byte */
    const DUKASCOPY_TICK_size = 20;


    /** @var ?HttpClient */
    protected ?HttpClient $httpClient = null;

    /** @var array<string, array<int|float>> - internal cache for history start times (fetched per symbol) */
    protected array $historyStarts = [];

    /** @var array<string, array<int|float>> - internal cache for all history start records (fetched together) */
    protected array $allHistoryStarts = [];

    /**
     * @var mixed[] - internal cache for downloaded Dukascopy data
     *                PRICE_BID|PRICE_ASK: DUKASCOPY_BAR
     *                PRICE_MEDIAN:        RT_POINT_BAR
     *
     * @phpstan-var array<string, array<array<array<array<DUKASCOPY_BAR|RT_POINT_BAR>>>>>
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     */
    protected array $history = [];


    /**
     * Return a Dukascopy specific HTTP client. The instance is kept in memory to support "keep-alive" connections.
     *
     * @return HttpClient
     */
    protected function getHttpClient(): HttpClient {
        return $this->httpClient ??= new DukascopyClient();
    }


    /**
     * Decompress a compressed Dukascopy data file and return its content.
     *
     * @param  string  $compressedFile    - name of the compressed data file
     * @param  ?string $saveAs [optional] - if specified the decompressed file is stored in a file with the given name
     *                                      (default: no additional storage)
     *
     * @return string - decompressed file content
     */
    protected function decompressFile(string $compressedFile, ?string $saveAs = null): string {
        return $this->decompressData(file_get_contents($compressedFile), $saveAs);
    }


    /**
     * Decompress a compressed Dukascopy data string and return it.
     *
     * @param  string  $data              - compressed string with bars or ticks
     * @param  ?string $saveAs [optional] - if specified the decompressed data is stored in a file with the given name
     *                                      (default: no additional storage)
     *
     * @return string - decompressed data
     */
    public function decompressData(string $data, ?string $saveAs = null): string {
        if (isset($saveAs) && !strlen($saveAs)) throw new InvalidValueException('Invalid parameter $saveAs: "" (empty)');

        $rawData = LZMA::decompressData($data);

        if (isset($saveAs)) {
            FS::mkDir(dirname($saveAs));
            $tmpFile = tempnam(dirname($saveAs), basename($saveAs));
            // write to a tmp file to make sure an existing final file can't be corrupt
            file_put_contents($tmpFile, $rawData);
            if (is_file($saveAs)) unlink($saveAs);
            rename($tmpFile, $saveAs);
        }
        return $rawData;
    }


    /**
     * Get history for the specified symbol, bar period and time. The range of the returned data depends on the requested
     * bar period.
     *
     * @param  DukascopySymbol $symbol             - symbol to get history data for
     * @param  int             $period             - bar period identifier: PERIOD_M1 | PERIOD_D1
     * @param  int             $time               - FXT time
     * @param  bool            $compact [optional] - returned bar format (default: more compact RT_POINT_BARs)
     *
     * @return         array<array<int|float>> - history or an empty array if history for the specified parameters is not available
     * @phpstan-return ($compact is true ? RT_POINT_BAR[] : RT_PRICE_BAR[])
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public function getHistory(DukascopySymbol $symbol, int $period, int $time, bool $compact = true): array {
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        $nameU = strtoupper($symbol->getName());
        $day = $time - $time % DAY;                                                         // 00:00 FXT

        if (!isset($this->history[$nameU][$period][$day][PRICE_MEDIAN])) {
            // load Bid and Ask, calculate Median and store everything in the cache
            if (!$bids = $this->loadHistory($symbol, $period, $day, PRICE_BID)) return [];  // raw
            if (!$asks = $this->loadHistory($symbol, $period, $day, PRICE_ASK)) return [];  // raw
            $median = $this->calculateMedian($bids, $asks);                                 // RT_POINT_BARs
            $this->history[$nameU][$period][$day][PRICE_MEDIAN] = $median;
        }

        // limit memory consumption
        $purges = array_diff(array_keys($this->history), [$nameU]);
        foreach ($purges as $stale) unset($this->history[$stale]);                          // drop old symbols
        $purges = array_diff(array_keys($this->history[$nameU][$period]), [$day-DAY, $day, $day+DAY]);
        foreach ($purges as $stale) unset($this->history[$nameU][$period][$stale]);         // drop old timeseries

        if ($compact) {
            return $this->history[$nameU][$period][$day][PRICE_MEDIAN];                     // RT_POINT_BARs
        }

        if (!isset($this->history[$nameU][$period][$day]['real'])) {
            $real = RT::convertPointToPriceBars($this->history[$nameU][$period][$day][PRICE_MEDIAN], $symbol->getPointValue());
            $this->history[$nameU][$period][$day]['real'] = $real;                          // PRICE_BARs
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
     * @return         array - array of DUKASCOPY_BARs or an empty array if history for the specified bar period/time is not available
     * @phpstan-return DUKASCOPY_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR
     */
    protected function loadHistory(DukascopySymbol $symbol, int $period, int $time, int $type): array {
        if ($period != PERIOD_M1)                     throw new InvalidValueException('Invalid parameter $period: '.periodToStr($period));
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
        $day          = $time - $time%DAY;                                              // 00:00 FXT
        $previousDay  = $day - 1*DAY;                                                   // 00:00 FXT
        $currentDayOk = $previousDayOk = false;

        if (isset($this->history[$nameU][$period][$day][$type])) {
            if (sizeof($this->history[$nameU][$period][$day][$type]) == PERIOD_D1) {    // that's if data of the day is complete
                return $this->history[$nameU][$period][$day][$type];
            }
            $first = first($this->history[$nameU][$period][$day][$type]);               // if cached data starts with 00:00, the
            $previousDayOk = ($first['time_delta'] === 0);                              // previous GMT day was already loaded

            $last = last($this->history[$nameU][$period][$day][$type]);                 // if cached data ends with 23:59, the
            $currentDayOk = ($last['time_delta'] === 23*HOURS+59*MINUTES);              // current GMT day was already loaded
        }

        // download and convert missing data
        foreach ([$previousDay => $previousDayOk, $day => $currentDayOk] as $day => $dayOk) {
            if (!$dayOk) {
                $data = $this->getHttpClient()->downloadHistory($name, $day, $type);    // here 00:00 FXT can be used as GMT
                if (!$data) return [];                                                  // that's if data is not available
                $data = $this->decompressData($data);
                $this->parseBarData($data, $symbol, $day, $period, $type);              // here 00:00 FXT can be used as GMT
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
     * @return void - bar format in the cache: DUKASCOPY_BAR
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR
     */
    protected function parseBarData(string $data, DukascopySymbol $symbol, int $day, int $period, int $type): void {
        if ($period != PERIOD_M1)                     throw new InvalidValueException('Invalid parameter $period: '.periodToStr($period));
        if (!in_array($type, [PRICE_BID, PRICE_ASK])) throw new InvalidValueException('Invalid parameter $type: '.$type);

        $symbolU = strtoupper($symbol->getName());
        $day -= $day % DAY;                                         // 00:00 GMT

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
        $fxtOffset = fxtOffset($day, $prev, $next);
        foreach ($bars as $i => $bar) {
            $timeGMT = $day + $bar['timeDelta'];
            if ($timeGMT >= $next['time']) {                        // If $day = "Sun, 00:00 GMT" bars may cover a DST transition.
                $fxtOffset = $next['offset'];                       // In this case $fxtOffset must be updated during the loop.
            }
            $time = $timeGMT + $fxtOffset;
            $bars[$i]['time'      ] = $time;                        // FXT
            $bars[$i]['time_delta'] = $time % DAY;                  // offset to 00:00 FXT
            unset($bars[$i]['timeDelta']);
        }

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
            $cache = &$this->history[$symbolU][$period][$day1][$type];
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
            $cache = &$this->history[$symbolU][$period][$day2][$type];
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
     * Calculate a PRICE_MEDIAN timeseries from the passed Bid and Ask history.
     *
     * @param          array<array<int|float>> $bids - Bid bars
     * @phpstan-param  DUKASCOPY_BAR[]         $bids
     *
     * @param          array<array<int|float>> $asks - Ask bars
     * @phpstan-param  DUKASCOPY_BAR[]         $asks
     *
     * @return         array<int[]> - timeseries array
     * @phpstan-return RT_POINT_BAR[]
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     */
    protected function calculateMedian(array $bids, array $asks): array {
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
     * Parse a file with raw Dukascopy bar data and convert it to an array.
     *
     * @param  string          $fileName - name of file with Dukascopy bar data
     * @param  DukascopySymbol $symbol   - symbol the data belongs to
     * @param  int             $type     - price type (for error messages as data may contain errors)
     * @param  int             $time     - time of the bar data (for error messages as data may contain errors)
     *
     * @return         array[] - list of arrays each representing a C++ struct DUKASCOPY_BAR
     * @phpstan-return DUKASCOPY_BAR_RAW[]
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR_RAW
     */
    protected function readBarFile(string $fileName, DukascopySymbol $symbol, int $type, int $time): array {
        return $this->readBarData(file_get_contents($fileName), $symbol, $type, $time);
    }


    /**
     * Parse a string with raw Dukascopy bar data and convert it to a timeseries array.
     *
     * @param  string          $data   - string with Dukascopy bar data
     * @param  DukascopySymbol $symbol - symbol the data belongs to
     * @param  int             $type   - price type (for error messages as data may contain errors)
     * @param  int             $time   - time of the history (for error messages as data may contain errors)
     *
     * @return         array[] - list of arrays each representing a C++ struct DUKASCOPY_BAR
     * @phpstan-return DUKASCOPY_BAR_RAW[]
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR_RAW
     */
    public function readBarData(string $data, DukascopySymbol $symbol, int $type, int $time): array {
        $lenData = strlen($data);
        if (!$lenData || $lenData % DUKASCOPY_BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' '.priceTypeDescription($type).' data: '.$lenData.' (not an even DUKASCOPY_BAR_SIZE)');

        static $isLittleEndian;
        $isLittleEndian ??= isLittleEndian();
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
     * Parse a file with Dukascopy tick data and convert it to an array.
     *
     * @param  string $fileName - name of file with Dukascopy tick data
     *
     * @return         array[] - array of DUKASCOPY_TICKs
     * @phpstan-return DUKASCOPY_TICK_RAW[]
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_TICK_RAW
     */
    public static function readTickFile(string $fileName): array {
        return self::readTickData(file_get_contents($fileName));
    }


    /**
     * Parse a string with Dukascopy tick data and convert it to an array.
     *
     * @param  string $data - string with Dukascopy tick data
     *
     * @return         array[] - array of DUKASCOPY_TICKs
     * @phpstan-return DUKASCOPY_TICK_RAW[]
     *
     * @see \rosasurfer\rt\phpstan\DUKASCOPY_TICK_RAW
     */
    public static function readTickData(string $data): array {
        $lenData = strlen($data);
        if (!$lenData || $lenData % DUKASCOPY_TICK_SIZE) throw new RuntimeException('Odd length of passed data: '.$lenData.' (not an even DUKASCOPY_TICK_SIZE)');
        $offset = 0;
        $ticks = [];
        $i = -1;

        static $isLittleEndian = null;
        $isLittleEndian ??= isLittleEndian();

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
     * Fetch history start times for the specified symbol.
     *
     * @param  DukascopySymbol $symbol
     *
     * @return array<int|float> - history start times per timeframe or an empty array in case of errors
     */
    public function fetchHistoryStart(DukascopySymbol $symbol): array {
        $symbolU = strtoupper($symbol->getName());

        return $this->allHistoryStarts[$symbolU] ?? $this->historyStarts[$symbolU] ?? (function() use ($symbol, $symbolU) {
            $name = $symbol->getName();
            Output::out('[Info]    '.str_pad($name, 6).'  downloading history start times from Dukascopy...');

            $data = $this->getHttpClient()->downloadHistoryStart($name);
            if (strlen($data)) {
                $this->historyStarts[$symbolU] = $this->readTimeframesSection($data, 0, PHP_INT_MAX);
            }
            return $this->historyStarts[$symbolU] ?? [];
        })();
    }


    /**
     * Fetch history start times from Dukascopy for all available symbols.
     *
     * @return array<string, array<int|float>> - array with timeframe start times per symbol
     *
     * <pre>
     * Array(
     *     '{symbol}' => [
     *         {timeframeId} => {starttime},        // e.g. PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *         {timeframeId} => {starttime},        //      PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *         {timeframeId} => {starttime},        //      PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *         {timeframeId} => {starttime},        //      PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     *     ],
     *     ...
     * )
     * </pre>
     */
    public function fetchHistoryStarts(): array {
        if ($this->allHistoryStarts) {
            return $this->allHistoryStarts;
        }
        Output::out('[Info]    Downloading history start times from Dukascopy...');

        $data = $this->getHttpClient()->downloadHistoryStart();
        if (strlen($data)) {
            return $this->allHistoryStarts = $this->readHistoryStarts($data);
        }
        return [];
    }


    /**
     * Parse a string with history start times of multiple symbols.
     *
     * @param  string $data - binary data
     *
     * @return array<string, array<int|float>> - array with timeframe start times per symbol
     *
     * <pre>
     * Array(
     *     '{symbol}' => [
     *         {timeframeId} => {starttime},        // e.g. PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *         {timeframeId} => {starttime},        //      PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *         {timeframeId} => {starttime},        //      PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *         {timeframeId} => {starttime},        //      PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     *     ],
     *     ...
     * )
     * </pre>
     */
    protected function readHistoryStarts(string $data): array {
        $lenData = strlen($data);
        if (!$lenData) throw new InvalidValueException('Illegal length of history start data: '.$lenData);

        $symbols = [];
        $start = $length = $highPart = $count = null;
        $symbol = '';
        $offset = 0;

        // unpack() format codes: https://www.php.net/manual/en/function.pack.php#refsect1-function.pack-parameters
        while ($offset < $lenData) {
            /** @var array{start:int, length:int} $vars */
            $vars = unpack("@$offset/Cstart/Clength", $data);
            extract($vars);
            if ($start)                     throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.$offset.': start='.$start);
            $offset += 2;

            /** @var array{symbol:string, highPart:int, count:int} $vars */
            $vars = unpack("@{$offset}/A{$length}symbol/NhighPart/Ncount", $data);
            extract($vars);
            if (strlen($symbol) != $length) throw new RuntimeException("Unexpected data format in DUKASCOPY_HISTORY_START at offset $offset: symbol=\"$symbol\"  length=$length");
            if ($highPart)                  throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.($offset+$length).": highPart=$highPart");
            if ($count != 4)                throw new RuntimeException('Unexpected data format in DUKASCOPY_HISTORY_START at offset '.($offset+$length+1).": count=$count");
            $offset += $length + 8;

            $timeframes = $this->readTimeframesSection($data, $offset, $count);
            if ($timeframes) {                                                  // skip symbols without history
                $symbols[strtoupper($symbol)] = $timeframes;
            }
            $offset += $count * self::DUKASCOPY_TIMEFRAME_size;
        }

        ksort($symbols);
        return $symbols;
    }


    /**
     * Parse a binary string and return an array with history start times per timeframe.
     *
     * @param  string $data   - binary data
     * @param  int    $offset - string offset to start reading
     * @param  int    $count  - number of records to read
     *
     * @return array<int|float> - array of timeframe start times
     */
    protected function readTimeframesSection(string $data, int $offset, int $count): array {
        $lenData = strlen($data);
        if ($offset < 0)         throw new InvalidValueException("Invalid parameter \$offset: $offset (expected non-negative value)");
        if ($offset >= $lenData) throw new InvalidValueException("Invalid parameter \$offset: $offset (out of range)");
        if ($count <= 0)         throw new InvalidValueException("Invalid parameter \$count: $count (expected positive value)");

        /** @var array<int|float> $timeframes */
        $timeframes = [];

        while ($offset < $lenData && $count) {
            if ($record = $this->readTimeframeRecord($data, $offset)) {
                $timeframes += $record;
            }
            $offset += self::DUKASCOPY_TIMEFRAME_size;
            $count--;
        }
        ksort($timeframes);
        return $timeframes;
    }


    /**
     * Parse and return a timeframe start record.
     *
     * @param  string $data   - binary string containing the record
     * @param  int    $offset - string offset to read the record from
     *
     * @return ?array<int|float> - timeframe start record or NULL if the data indicates "no history available"
     */
    protected function readTimeframeRecord(string $data, int $offset): ?array {
        // @see  https://www.php.net/manual/en/function.pack.php#refsect1-function.pack-parameters

        // check PHP platform
        if (PHP_INT_SIZE == 8) {
            // x64: 64-bit format codes are supported
            $record = unpack("@$offset/J2", $data);
            if ($record[1] == -1) {                                             // reset uint64_max (sometimes used as tickdata identifier)
                $record[1] = 0;
            }
            $timeframe = $record[1] / 1000 / MINUTES;
            if (!is_int($timeframe) || (string)$timeframe==periodToStr($timeframe)) {
                throw new RuntimeException("Unexpected Dukascopy timeframe identifier: $record[1]");
            }
            $record[1] = $timeframe;
            if ($record[2] < 0) throw new RangeException('Invalid Java timestamp: '.sprintf('%u', $record[2]).' (out of range)');
            if ($record[2] == PHP_INT_MAX) {                                    // int64_max = no history available
                return null;
            }
            if ($record[2] % 1000) $record[2] = round($record[2]/1000, 3);
            else                   $record[2] = (int)($record[2]/1000);
        }
        else {
            // x32: 64-bit format codes are not supported
            $ints = unpack("@$offset/N4", $data);
            $record = [];
            foreach ($ints as $i => $int) {
                $int = sprintf('%u', $int);
                if ($i % 2) $record[($i+1)/2] = bcmul($int, '4294967296', 0);   // 2 ^ 32
                else        $record[ $i=$i/2] = bcadd($record[$i], $int, 0);
            }
            if ($record[1] == '18446744073709551615') {                         // reset uint64_max (sometimes used as tickdata identifier)
                $record[1] = '0';
            }
            $timeframe = ((int)bcdiv($record[1], '1000', 0)) / MINUTES;
            if (!is_int($timeframe) || (string)$timeframe==periodToStr($timeframe)) {
                throw new RuntimeException('Unexpected Dukascopy timeframe identifier: '.$record[1]);
            }
            $record[1] = $timeframe;
            if ($record[2] == '9223372036854775807') {                          // int64_max = no history available
                return null;
            }
            if (!bcmod($record[2], '1000')) $record[2] =   (int) bcdiv($record[2], '1000', 0);
            else                            $record[2] = (float) bcdiv($record[2], '1000', 3);
        }

        if ($record[1] == PERIOD_D1) $record[2] -= ($record[2] % DAY);
        else                         $record[2]  = fxTime($record[2]);

        return [$record[1] => $record[2]];
    }
}
