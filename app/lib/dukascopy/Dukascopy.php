<?php
namespace rosasurfer\rt\dukascopy;

use rosasurfer\core\Object;
use rosasurfer\exception\IllegalArgumentException;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\log\Logger;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rt\LZMA;
use rosasurfer\rt\model\DukascopySymbol;

use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\DUKASCOPY_BAR_SIZE;
use const rosasurfer\rt\DUKASCOPY_TICK_SIZE;
use const rosasurfer\rt\PERIOD_M1;


/**
 * Dukascopy
 *
 * Functionality related to downloading and processing Dukascopy history data.
 *
 *
 * struct big-endian DUKASCOPY_BAR {    // -- offset --- size --- description -----------------------------------------------
 *     uint  timeDelta;                 //         0        4     time difference in seconds since 00:00 GMT
 *     uint  open;                      //         4        4     in point
 *     uint  close;                     //         8        4     in point
 *     uint  low;                       //        12        4     in point
 *     uint  high;                      //        16        4     in point
 *     float volume;                    //        20        4
 * };                                   // ----------------------------------------------------------------------------------
 *                                      //               = 24
 *
 * struct big-endian DUKASCOPY_TICK {   // -- offset --- size --- description -----------------------------------------------
 *     uint  timeDelta;                 //         0        4     time difference in milliseconds since start of the hour
 *     uint  ask;                       //         4        4     in point
 *     uint  bid;                       //         8        4     in point
 *     float askSize;                   //        12        4     cumulated ask size in lot (min. 1)
 *     float bidSize;                   //        16        4     cumulated bid size in lot (min. 1)
 * };                                   // ----------------------------------------------------------------------------------
 *                                      //               = 20
 */
class Dukascopy extends Object implements IDukascopyService {


    /** @var HttpClient */
    protected $httpClient;


    /**
     * Resolve and return a Dukascopy specific HTTP client. The instance is kept in memory to enable "keep-alive" connections.
     *
     * @return HttpClient
     */
    protected function getHttpClient() {
        if (!$this->httpClient) {
            $options = [];
            $options[CURLOPT_SSL_VERIFYPEER] = false;       // suppress SSL certificate validation errors
            //$options[CURLOPT_VERBOSE     ] = true;
            $this->httpClient = new HttpClient($options);
        }
        return $this->httpClient;
    }


    /**
     * Fetch history start for the specified symbol from Dukascopy.
     *
     * @param  string $symbol
     *
     * @return int - FXT timestamp or 0 (zero) if history start info is not available
     */
    public function fetchHistoryStart($symbol) {
        $data = $this->downloadHistoryStart($symbol);
        //$root = $this->di()['config']['app.dir.root'];
        //$data = file_get_contents($root.'/bin/dukascopy/HistoryStart.AUDUSD.bi5');

        if (strlen($data)) {
            $times = $this->readHistoryStart($data);
            $dates = [];
            foreach ($times as $timeframe => $time) {
                $datetime = \DateTime::createFromFormat(is_int($time) ? 'U':'U.u', is_int($time) ? (string)$time : number_format($time, 6, '.', ''));
                $dates[periodToStr($timeframe)] = $datetime->format('D, d-M-Y H:i:s'.(is_int($time) ? '':'.u'));
            }
            echoPre($dates);
            return $times[PERIOD_M1];
        }
        return 0;
    }


    /**
     * Load history start data for the specified symbol.
     *
     * @param  string $symbol
     *
     * @return string - raw binary history start data or an empty string in case of errors
     */
    protected function downloadHistoryStart($symbol) {
        $client = $this->getHttpClient();

        $url = 'http://datafeed.dukascopy.com/datafeed/'.$symbol.'/metadata/HistoryStart.bi5';

        $request  = new HttpRequest($url);
        $response = $client->send($request);
        $status   = $response->getStatus();
        if ($status!=200 && $status!=404) throw new RuntimeException('Unexpected HTTP status '.$status.' ('.HttpResponse::$sc[$status].') for url "'.$url.'"'.NL.printPretty($response, true));

        // treat an empty response as error 404
        $content = $response->getContent();
        if (!strlen($content))
            $status = 404;
        if ($status == 404) echoPre('[Error]   URL not found (404): '.$url);

        return ($status==200) ? $response->getContent() : '';
    }


    /**
     * Decompress a compressed Dukascopy data string and return it.
     *
     * @param  string $data              - compressed string with bars or ticks
     * @param  string $saveAs [optional] - if specified the decompressed string is additionally stored in the given file
     *                                     (default: no storage)
     *
     * @return string - decompressed data string
     */
    public static function decompressHistoryData($data, $saveAs = null) {
        if (!is_string($data))       throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        if (isset($saveAs)) {
            if (!is_string($saveAs)) throw new IllegalTypeException('Illegal type of parameter $saveAs: '.gettype($saveAs));
            if (!strlen($saveAs))    throw new InvalidArgumentException('Invalid parameter $saveAs: ""');
        }

        $rawData = LZMA::decompressData($data);

        if (isset($saveAs)) {
            mkDirWritable(dirname($saveAs));
            $tmpFile = tempnam(dirname($saveAs), basename($saveAs));
            file_put_contents($tmpFile, $rawData);
            if (is_file($saveAs)) unlink($saveAs);
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
    public static function decompressHistoryFile($compressedFile, $saveAs = null) {
        if (!is_string($compressedFile)) throw new IllegalTypeException('Illegal type of parameter $compressedFile: '.gettype($compressedFile));
        return self::decompressHistoryData(file_get_contents($compressedFile), $saveAs);
    }


    /**
     * Parse a string with Dukascopy bar data and convert it to a timeseries array.
     *
     * @param  string $data   - string with Dukascopy bar data
     * @param  string $symbol - Dukascopy symbol
     * @param  string $type   - meta info for error message generation
     * @param  int    $time   - ditto
     *
     * @return array[] - DUKASCOPY_BAR[] data as a timeseries array
     */
    public static function readBarData($data, $symbol, $type, $time) {
        /** @var DukascopySymbol $dukaSymbol */
        $dukaSymbol = DukascopySymbol::dao()->getByName($symbol);
        $symbol     = $dukaSymbol->getName();
        $digits     = $dukaSymbol->getDigits();
        $divider    = pow(10, $digits);

        if (!is_string($data))                        throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        $lenData = strlen($data);
        if (!$lenData || $lenData%DUKASCOPY_BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol.' '.$type.' data: '.$lenData.' (not an even DUKASCOPY_BAR_SIZE)');

        $offset  = 0;
        $bars    = [];
        $i       = -1;

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

                Logger::log("Illegal ".$symbol." $type data for bar[$i] of ".gmdate('D, d-M-Y H:i:s', $time).": O=$O H=$H L=$L C=$C, adjusting high/low...", L_WARN);

                $bars[$i]['high'] = max($bars[$i]['open'], $bars[$i]['high'], $bars[$i]['low'], $bars[$i]['close']);
                $bars[$i]['low' ] = min($bars[$i]['open'], $bars[$i]['high'], $bars[$i]['low'], $bars[$i]['close']);
            }
        }
        return $bars;
    }


    /**
     * Parse a file with Dukascopy bar data and convert it to a data array.
     *
     * @param  string $fileName - name of file with Dukascopy bar data
     * @param  string $symbol   - meta infos for generating better error messages (Dukascopy data may contain errors)
     * @param  string $type     - ...
     * @param  int    $time     - ...
     *
     * @return array - DUKASCOPY_BAR[] data
     */
    public static function readBarFile($fileName, $symbol, $type, $time) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.gettype($fileName));
        return self::readBarData(file_get_contents($fileName), $symbol, $type, $time);
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
        return self::readTickData(file_get_contents($fileName));
    }


    /**
     * Parse a string with a symbol's history start data.
     *
     * @param  string $data - raw binary data
     *
     * @return array - array with variable number of elements each describing history start of a single timeframe
     *                 as follows:
     * <pre>
     * Array [
     *     '{timeframe-id}' => {timestamp},     // e.g.: 'PERIOD_M1'    => Sun, 03-Aug-2003 21:00:00
     *     '{timeframe-id}' => {timestamp},     //       'PERIOD_TICKS' => Sun, 03-Aug-2003 21:00:05.044
     *     ...                                  //
     * ]
     * </pre>
     */
    public static function readHistoryStart($data) {
        if (!is_string($data))          throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        $lenData = strlen($data);
        if (!$lenData || $lenData % 16) throw new IllegalArgumentException('Illegal length of history start data: '.$lenData);

        $times = [];

        if (PHP_INT_SIZE == 8) {                // 64-bit integers
            $offset = 0;
            $i      = -1;
            while ($offset < $lenData) {
                ++$i;
                $record = unpack("@$offset/J2", $data);
                $timeframe = $record[1] / 1000 / MINUTES;
                if (!is_int($timeframe) || (string)$timeframe==periodToStr($timeframe)) throw new InvalidArgumentException('Unknown Dukascopy history timeframe identifier: '.$record[1]);
                $record[1] = $timeframe;
                if ($record[2] < 0) throw new \RangeException('Invalid Java timestamp: '.sprintf('%u', $record[2]).' (out of range)');
                if ($record[2] % 1000) $record[2] = round($record[2]/1000, 3);
                else                   $record[2] = (int)($record[2]/1000);
                $times[$record[1]] = $record[2];
                $offset += 16;
            }
        }
        else {                                  // 32-bit integers: 64-bit format codes are not supported
            $offset = 0;
            $i      = -1;
            while ($offset < $lenData) {
                ++$i;
                $ints = unpack("@$offset/N4", $data);
                $record = [];
                foreach ($ints as $i => $int) {
                    $int = sprintf('%u', $int);
                    if ($i % 2) $record[($i+1)/2] = bcmul($int, '4294967296', 0);   // 2 ^ 32
                    else        $record[ $i=$i/2] = bcadd($record[$i], $int, 0);
                }
                /** @var int $timeframe */
                $timeframe = ((int) bcdiv($record[1], '1000', 0)) / MINUTES;
                if (!is_int($timeframe) || (string)$timeframe==periodToStr($timeframe)) throw new InvalidArgumentException('Unknown Dukascopy history timeframe identifier: '.$record[1]);
                $record[1] = $timeframe;
                if (!bcmod($record[2], '1000')) $record[2] =   (int) bcdiv($record[2], '1000', 0);
                else                            $record[2] = (float) bcdiv($record[2], '1000', 3);
                $times[$record[1]] = $record[2];
                $offset += 16;
            }
        }
        return $times;
    }
}
