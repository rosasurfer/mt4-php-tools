<?php
namespace rosasurfer\rt\lib\dukascopy;

use rosasurfer\console\io\Output;
use rosasurfer\core\Object;
use rosasurfer\exception\IllegalArgumentException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\file\FileSystem as FS;
use rosasurfer\log\Logger;

use rosasurfer\rt\lib\LZMA;
use rosasurfer\rt\lib\dukascopy\HttpClient as DukascopyClient;
use rosasurfer\rt\model\DukascopySymbol;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\fxTimezoneOffset;
use function rosasurfer\rt\periodDescription;
use function rosasurfer\rt\periodToStr;
use function rosasurfer\rt\priceTypeDescription;

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
class Dukascopy extends Object {


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
     * Fetch history start for the specified symbol.
     *
     * @param  DukascopySymbol $symbol
     *
     * @return array - history start times per timeframe or an empty value in case of errors
     *
     * <pre>
     * Array (
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

        /** @var Output $output */
        $output = $this->di(Output::class);
        $output->out('[Info]    '.str_pad($name, 6).'  downloading history start times from Dukascopy...');

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
     * Array (
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

        /** @var Output $output */
        $output = $this->di(Output::class);
        $output->out('[Info]    Downloading history start times from Dukascopy...');

        $data = $this->getHttpClient()->downloadHistoryStart();
        if (strlen($data))
            return $this->allHistoryStarts = $this->readHistoryStarts($data);
        return [];
    }


    /**
     * Get history for the specified symbol, period and time. Downloads required data and converts Dukascopy GMT times
     * to FXT. The covered range of the returned timeseries depends on the requested bar period.
     *
     * @param  DukascopySymbol $symbol
     * @param  int             $period - [PERIOD_M1 | PERIOD_D1]
     * @param  int             $time   - FXT time
     * @param  int             $type   - [PRICE_BID | PRICE_ASK | PRICE_MEDIAN]
     *
     * @return array[] - If history for the specified period and time is not available an empty array is returned. Otherwise
     *                   a timeseries array is returned with each element describing a single price bar as follows:
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time (FXT)
     *     'open'  => (float),          // open value
     *     'high'  => (float),          // high value
     *     'low'   => (float),          // low value
     *     'close' => (float),          // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ];
     * </pre>
     */
    public function getHistory(DukascopySymbol $symbol, $period, $time, $type) {
        if (!is_int($period))                                       throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period != PERIOD_M1)                                   throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        if (!is_int($time))                                         throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if (!is_int($type))                                         throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));
        if (!in_array($type, [PRICE_BID, PRICE_ASK, PRICE_MEDIAN])) throw new InvalidArgumentException('Invalid parameter $type: '.$type);
        $time -= $time%DAY;

        // make sure Bid and Ask are loaded and Median is calculated
        foreach ([PRICE_BID, PRICE_ASK] as $reqType) {
            $this->loadHistory($symbol, $period, $time, $reqType);
        }                                                               // As Bid or Ask will never be requested alone we
        $this->calculateMedian($symbol, $period, $time);                // may as well pre-load and calculate everything.

        // return resulting data from cache
        $nameU = strtoupper($symbol->getName());
        return $this->history[$nameU][$period][$time][$type] ?: [];
    }


    /**
     * Download history data from Dukascopy for the specified time and period, convert GMT times to FXT and store the resulting
     * timeseries in the internal bar cache.
     *
     * @param  DukascopySymbol $symbol
     * @param  int             $period - PERIOD_M1 | PERIOD_D1
     * @param  int             $time   - FXT time
     * @param  int             $type   - PRICE_BID | PRICE_ASK
     */
    protected function loadHistory(DukascopySymbol $symbol, $period, $time, $type) {
        if (!is_int($period))                         throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period != PERIOD_M1)                     throw new InvalidArgumentException('Invalid parameter $period: '.periodToStr($period));
        if (!is_int($time))                           throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if (!is_int($type))                           throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));
        if (!in_array($type, [PRICE_BID, PRICE_ASK])) throw new InvalidArgumentException('Invalid parameter $type: '.$type);

        // Day transition time (Midnight) for Dukascopy data is at 00:00 GMT (~02:00 FXT). Thus each FXT day requires
        // Dukascopy data of the current and the previous GMT day. If all data is already present in the internal cache
        // this method does nothing. Otherwise data is downloaded, converted and stored in the cache.
        //
        //         +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        // GMT:    |   Sun   ||   Mon   |   Tue   |   Wed   |   Thu   |   Fri   ||   Sat   |   Sun   ||   Mon   |
        //         +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        //      +---------++---------+---------+---------+---------+---------++---------+---------++---------+
        // FXT: |   Sun   ||   Mon   |   Tue   |   Wed   |   Thu   |   Fri   ||   Sat   |   Sun   ||   Mon   |
        //      +---------++---------+---------+---------+---------+---------++---------+---------++---------+

        $name  = $symbol->getName();
        $nameU = strtoupper($name);
        $day          = $time - $time%DAY;
        $previousDay  = $day - 1*DAY;
        $currentDayOk = $previousDayOk = false;

        if (isset($this->history[$nameU][$period][$day][$type])) {
            if (sizeof($this->history[$nameU][$period][$day][$type]) == PERIOD_D1)  // that's if data of the day is complete
                return;

            $first = first($this->history[$nameU][$period][$day][$type]);           // if cached data starts with 00:00, the
            $previousDayOk = ($first['delta_fxt'] === 0);                           // previous GMT day was already loaded

            $last = last($this->history[$nameU][$period][$day][$type]);             // if cached data ends with 23:59, the
            $currentDayOk = ($last['delta_fxt'] === 23*HOURS+59*MINUTES);           // current GMT day was already loaded
        }

        // download and convert missing data
        foreach ([$previousDay=>$previousDayOk, $day=>$currentDayOk] as $day => $dayOk) {
            if (!$dayOk) {
                $data = $this->getHttpClient()->downloadHistory($name, $day, $type);
                if (!$data) return;                                                 // that's if data is not available
                $data = $this->decompressData($data);
                $this->parseBarData($data, $symbol, $day, $period, $type);
            }
        }
    }


    /**
     * Parse a string with binary history data for the specified time and period, convert GMT times to FXT and store the
     * resulting timeseries in the internal bar cache.
     *
     * @param  string          $data   - binary history data
     * @param  DukascopySymbol $symbol - symbol the data belongs to
     * @param  int             $day    - FXT day of the history
     * @param  int             $period - atm. only PERIOD_M1 is supported
     * @param  int             $type   - PRICE_BID | PRICE_ASK
     */
    protected function parseBarData($data, DukascopySymbol $symbol, $day, $period, $type) {
        if (!is_string($data))                        throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        if (!is_int($day))                            throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
        if (!is_int($period))                         throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period != PERIOD_M1)                     throw new InvalidArgumentException('Invalid parameter $period: '.periodToStr($period));
        if (!is_int($type))                           throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));
        if (!in_array($type, [PRICE_BID, PRICE_ASK])) throw new InvalidArgumentException('Invalid parameter $type: '.$type);

        /** @var Output $output */
        $output  = $this->di(Output::class);
        $symbolU = strtoupper($symbol->getName());
        $day    -= $day % DAY;

        // read bars
        $bars = $this->readBarData($data, $symbol, $type, $day);
        if (sizeof($bars) != PERIOD_D1) throw new RuntimeException('Unexpected number of Dukascopy bars in '.periodDescription($period).' data: '
                                                                   .sizeof($bars).' ('.(sizeof($bars) > PERIOD_D1 ? 'more':'less').' then a day)');
        // add timestamps and FXT data, drop field 'timeDelta'
        $prev = $next = null;
        $fxtOffset = fxTimezoneOffset($day, $prev, $next);
        foreach ($bars as &$bar) {
            $bar['time_gmt' ] = $day + $bar['timeDelta'];
            $bar['delta_gmt'] =        $bar['timeDelta'];
            if ($bar['time_gmt'] >= $next['time'])                  // If $day = "Sun, 00:00 GMT" bars may cover a DST transition.
                $fxtOffset = $next['offset'];                       // In that case $fxtOffset must be updated during the loop.
            $bar['time_fxt' ] = $bar['time_gmt'] + $fxtOffset;
            $bar['delta_fxt'] = $bar['time_fxt'] % DAY;
            unset($bar['timeDelta']);
        }; unset($bar);

        // resolve array offset of 00:00 FXT
        $newDayOffset = PERIOD_D1 - $fxtOffset/MINUTES;
        if ($fxtOffset == $next['offset']) {                        // additional lot check if bars cover a DST transition
            $firstBar = $bars[$newDayOffset];
            $lastBar  = $bars[$newDayOffset-1];
            if ($lastBar['lots'] /*|| !$firstBar['lots']*/) {
                $output->out('[Warn]    '.gmdate('D, d-M-Y', $day).'   lots mis-match during DST change.');
                $output->out('Day of DST change ('.gmdate('D, d-M-Y', $lastBar['time_fxt']).') ended with:');
                $output->out($bars[$newDayOffset-1]);
                $output->out('Day after DST change ('.gmdate('D, d-M-Y', $firstBar['time_fxt']).') started with:');
                $output->out($bars[$newDayOffset]);
            }
        }

        // store bars in cache
        $bars1 = array_slice($bars, 0, $newDayOffset);
        $bars2 = array_slice($bars, $newDayOffset);
        $day1 = $bars1[0]['time_fxt'] - $bars1[0]['delta_fxt'];
        $day2 = $bars2[0]['time_fxt'] - $bars2[0]['delta_fxt'];

        if (isset($this->history[$symbolU][$period][$day1][$type])) {
            // check that bars to merge match
            $cache =& $this->history[$symbolU][$period][$day1][$type];
            $lastBarTime = last($cache)['time_fxt'];
            $nextBarTime = $bars1[0]['time_fxt'];
            if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: '.priceTypeDescription($type).', $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
            $cache = array_merge($cache, $bars1);
        }
        else {
            $this->history[$symbolU][$period][$day1][$type] = $bars1;
        }

        if (isset($this->history[$symbolU][$period][$day2][$type])) {
            // check that bars to merge match
            $cache =& $this->history[$symbolU][$period][$day2][$type];
            $lastBarTime = last($bars2)['time_fxt'];
            $nextBarTime = $cache[0]['time_fxt'];
            if ($lastBarTime + 1*MINUTE != $nextBarTime) throw new RuntimeException('Bar time mis-match, bars to merge: '.priceTypeDescription($type).', $lastBarTime='.$lastBarTime.', $nextBarTime='.$nextBarTime);
            $cache = array_merge($bars2, $cache);
        }
        else {
            $this->history[$symbolU][$period][$day2][$type] = $bars2;
        }
    }


    /**
     * Calculate PRICE_MEDIAN of the specified history and store the resulting timeseries in the internal bar cache.
     *
     * @param  DukascopySymbol $symbol
     * @param  int             $period - PERIOD_M1 | PERIOD_D1
     * @param  int             $time   - FXT time
     */
    protected function calculateMedian(DukascopySymbol $symbol, $period, $time) {
    }


    /**
     * Decompress a compressed Dukascopy data string and return it.
     *
     * @param  string $data              - compressed string with bars or ticks
     * @param  string $saveAs [optional] - name of file to store the decompressed data (default: no storage)
     *
     * @return string - decompressed data
     */
    public function decompressData($data, $saveAs = null) {
        if (!is_string($data))       throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        if (isset($saveAs)) {
            if (!is_string($saveAs)) throw new IllegalTypeException('Illegal type of parameter $saveAs: '.gettype($saveAs));
            if (!strlen($saveAs))    throw new InvalidArgumentException('Invalid parameter $saveAs: ""');
        }

        $rawData = LZMA::decompressData($data);

        if (isset($saveAs)) {
            FS::mkDir(dirname($saveAs));
            $tmpFile = tempnam(dirname($saveAs), basename($saveAs));
            file_put_contents($tmpFile, $rawData);                      // make sure an interruption of the write process
            if (is_file($saveAs)) unlink($saveAs);                      // doesn't leave a corrupt file
            rename($tmpFile, $saveAs);
        }
        return $rawData;
    }


    /**
     * Decompress a compressed Dukascopy data file and return its content.
     *
     * @param  string $compressedFile    - name of the compressed data file
     * @param  string $saveAs [optional] - if specified the decompressed content is additionally stored in the given file
     *                                     (default: no storage)
     *
     * @return string - decompressed file content
     */
    public function decompressFile($compressedFile, $saveAs = null) {
        if (!is_string($compressedFile)) throw new IllegalTypeException('Illegal type of parameter $compressedFile: '.gettype($compressedFile));
        return $this->decompressData(file_get_contents($compressedFile), $saveAs);
    }


    /**
     * Parse a string with Dukascopy bar data and convert it to a timeseries array.
     *
     * @param  string          $data   - string with Dukascopy bar data
     * @param  DukascopySymbol $symbol - symbol the data belongs to
     * @param  int             $type   - price type (for error messages as data may contain errors)
     * @param  int             $time   - time of the history (for error messages as data may contain errors)
     *
     * @return array[] - DUKASCOPY_BAR[] data (an array of PHP representations of struct DUKASCOPY_BAR)
     */
    public static function readBarData($data, DukascopySymbol $symbol, $type, $time) {
        $digits  = $symbol->getDigits();
        $divider = pow(10, $digits);

        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        $lenData = strlen($data);
        if (!$lenData || $lenData%DUKASCOPY_BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol->getName().' '.priceTypeDescription($type).' data: '.$lenData.' (not an even DUKASCOPY_BAR_SIZE)');

        $offset =  0;
        $bars   = [];
        $i      = -1;

        static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();

        while ($offset < $lenData) {
            $i++;
            $bars[] = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh", $data);
            $s      = substr($data, $offset+20, 4);
            $lots   = unpack('f', $isLittleEndian ? strrev($s) : $s);   // unpack doesn't support explicit big-endian floats, on little-endian
            $bars[$i]['lots'] = round($lots[1], 2);                     // machines the byte order of field "lots" must be reversed manually
            $offset += DUKASCOPY_BAR_SIZE;

            // validate bar data
            if ($bars[$i]['open' ] > $bars[$i]['high'] ||               // from (H >= O && O >= L) follws (H >= L)
                $bars[$i]['open' ] < $bars[$i]['low' ] ||               // don't use min()/max() as it's slow
                $bars[$i]['close'] > $bars[$i]['high'] ||
                $bars[$i]['close'] < $bars[$i]['low' ]) {

                $O = number_format($bars[$i]['open' ]/$divider, $digits);
                $H = number_format($bars[$i]['high' ]/$divider, $digits);
                $L = number_format($bars[$i]['low'  ]/$divider, $digits);
                $C = number_format($bars[$i]['close']/$divider, $digits);

                Logger::log('Illegal '.$symbol->getName().' '.priceTypeDescription($type)." data for bar[$i] of ".gmdate('D, d-M-Y H:i:s', $time).": O=$O H=$H L=$L C=$C, adjusting high/low...", L_WARN);

                $bars[$i]['high'] = max($bars[$i]['open'], $bars[$i]['high'], $bars[$i]['low'], $bars[$i]['close']);
                $bars[$i]['low' ] = min($bars[$i]['open'], $bars[$i]['high'], $bars[$i]['low'], $bars[$i]['close']);
            }
        }
        return $bars;
    }


    /**
     * Parse a file with Dukascopy bar data and convert it to a data array.
     *
     * @param  string          $fileName - name of file with Dukascopy bar data
     * @param  DukascopySymbol $symbol   - symbol the data belongs to
     * @param  int             $type     - price type (for error messages as data may contain errors)
     * @param  int             $time     - time of the bar data (for error messages as data may contain errors)
     *
     * @return array[] - DUKASCOPY_BAR[] data (an array of PHP representations of struct DUKASCOPY_BAR)
     */
    public function readBarFile($fileName, DukascopySymbol $symbol, $type, $time) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.gettype($fileName));
        return $this->readBarData(file_get_contents($fileName), $symbol, $type, $time);
    }


    /**
     * Parse a string with Dukascopy tick data and convert it to a data array.
     *
     * @param  string $data - string with Dukascopy tick data
     *
     * @return array - DUKASCOPY_TICK[] data
     */
    public static function readTickData($data) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));

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
     * Parse a file with Dukascopy tick data and convert it to a data array.
     *
     * @param  string $fileName - name of file with Dukascopy tick data
     *
     * @return array - DUKASCOPY_TICK[] data
     */
    public static function readTickFile($fileName) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.gettype($fileName));
        return static::readTickData(file_get_contents($fileName));
    }


    /**
     * Parse a string with history start records of multiple symbols.
     *
     * @param  string $data - binary data
     *
     * @return array[] - associative list of arrays with variable number of elements each describing a symbol's history start
     *                   of a single timeframe as follows:
     * <pre>
     * Array (
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
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        $lenData = strlen($data);
        if (!$lenData)         throw new IllegalArgumentException('Illegal length of history start data: '.$lenData);

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
     * Array (
     *     [{period-id}] => [{timestamp}],              // e.g.: PERIOD_TICK => Mon, 04-Aug-2003 10:03:02.837,
     *     [{period-id}] => [{timestamp}],              //       PERIOD_M1   => Mon, 04-Aug-2003 10:03:00,
     *     [{period-id}] => [{timestamp}],              //       PERIOD_H1   => Mon, 04-Aug-2003 10:00:00,
     *     ...                                          //       PERIOD_D1   => Mon, 25-Nov-1991 00:00:00,
     * )
     * </pre>
     */
    protected function readHistoryStartSection($data, $offset = 0, $count = null) {
        $lenData = strlen($data);
        if (!is_int($offset) || $offset < 0)    throw new IllegalArgumentException('Invalid parameter $offset: '.$offset.' ('.gettype($offset).')');
        if ($offset >= $lenData)                throw new IllegalArgumentException('Invalid parameters, mis-matching $offset/$lenData: '.$offset.'/'.$lenData);
        if (!isset($count)) $count = PHP_INT_MAX;
        elseif (!is_int($count) || $count <= 0) throw new IllegalArgumentException('Invalid parameter $count: '.$count.' ('.gettype($count).')');

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
