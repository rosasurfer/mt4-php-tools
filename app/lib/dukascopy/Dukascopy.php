<?php
namespace rosasurfer\rt\dukascopy;

use rosasurfer\core\Object;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\log\Logger;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rt\LZMA;
use rosasurfer\rt\model\DukascopySymbol;

use const rosasurfer\rt\DUKASCOPY_BAR_SIZE;
use const rosasurfer\rt\DUKASCOPY_TICK_SIZE;


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
class Dukascopy extends Object {


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
     * Fetch history start infos for the specified symbol.
     *
     * @param  string $symbol
     *
     * @return int - FXT timestamp or 0 (zero) if history status information is not available
     */
    public function fetchHistoryStart($symbol) {
        $data = $this->downloadHistoryStart($symbol);
        echoPre($data);
        return 0;
    }


    /**
     * Laedt eine Dukascopy-M1-Datei und gibt ihren Inhalt zurueck.
     *
     * @param  string $symbol
     *
     * @return string - downloaded content or an empty string on download errors
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
        if (!strLen($content))
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
        if (!is_string($data))       throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
        if (isSet($saveAs)) {
            if (!is_string($saveAs)) throw new IllegalTypeException('Illegal type of parameter $saveAs: '.getType($saveAs));
            if (!strLen($saveAs))    throw new InvalidArgumentException('Invalid parameter $saveAs: ""');
        }

        $rawData = LZMA::decompressData($data);

        if (isSet($saveAs)) {
            mkDirWritable(dirName($saveAs));
            $tmpFile = tempNam(dirName($saveAs), baseName($saveAs));
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
        if (!is_string($compressedFile)) throw new IllegalTypeException('Illegal type of parameter $compressedFile: '.getType($compressedFile));
        return self::decompressHistoryData(file_get_contents($compressedFile), $saveAs);
    }


    /**
     * Parse a string with Dukascopy bar data and convert it to a data array.
     *
     * @param  string $data   - string with Dukascopy bar data
     * @param  string $symbol - Dukascopy symbol
     * @param  string $type   - meta infos for generating better error messages (Dukascopy data may contain errors)
     * @param  int    $time   - ditto
     *
     * @return array - DUKASCOPY_BAR[] data
     */
    public static function readBarData($data, $symbol, $type, $time) {
        /** @var DukascopySymbol $dukaSymbol */
        $dukaSymbol = DukascopySymbol::dao()->getByName($symbol);
        $symbol     = $dukaSymbol->getName();
        $digits     = $dukaSymbol->getDigits();
        $divider    = pow(10, $digits);

        if (!is_string($data))                        throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));
        $lenData = strLen($data);
        if (!$lenData || $lenData%DUKASCOPY_BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol.' '.$type.' data: '.$lenData.' (not an even DUKASCOPY_BAR_SIZE)');

        $offset  = 0;
        $bars    = [];
        $i       = -1;

        static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();

        // pack/unpack don't support explicit big-endian floats, on little-endian machines the byte order
        // of field "lots" has to be reversed manually
        while ($offset < $lenData) {
            $i++;
            $bars[] = unpack("@$offset/NtimeDelta/Nopen/Nclose/Nlow/Nhigh", $data);
            $s      = subStr($data, $offset+20, 4);
            $lots   = unpack('f', $isLittleEndian ? strRev($s) : $s);   // manually reverse on little-endian machines
            $bars[$i]['lots'] = round($lots[1], 2);
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

                Logger::log("Illegal ".$symbol." $type data for bar[$i] of ".gmDate('D, d-M-Y H:i:s', $time).": O=$O H=$H L=$L C=$C, adjusting high/low...", L_WARN);

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
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
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
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

        $lenData = strLen($data); if (!$lenData || $lenData%DUKASCOPY_TICK_SIZE) throw new RuntimeException('Odd length of passed data: '.$lenData.' (not an even DUKASCOPY_TICK_SIZE)');
        $offset  = 0;
        $ticks   = [];
        $i       = -1;

        static $isLittleEndian = null; is_null($isLittleEndian) && $isLittleEndian=isLittleEndian();

        // pack/unpack don't support explicit big-endian floats, on little-endian machines the byte order
        // of fields "bidSize" and "askSize" has to be reversed manually
        while ($offset < $lenData) {
            $i++;
            $ticks[] = unpack("@$offset/NtimeDelta/Nask/Nbid", $data);
            $s1      = subStr($data, $offset+12, 4);
            $s2      = subStr($data, $offset+16, 4);
            $size    = unpack('fask/fbid', $isLittleEndian ? strRev($s1).strRev($s2) : $s1.$s2);    // manually reverse
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
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
        return self::readTickData(file_get_contents($fileName));
    }
}
