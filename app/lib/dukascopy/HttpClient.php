<?php
namespace rosasurfer\rt\lib\dukascopy;

use rosasurfer\console\io\Output;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rt\lib\dukascopy\HttpRequest as DukascopyRequest;

use const rosasurfer\rt\PRICE_BID;
use const rosasurfer\rt\PRICE_ASK;
use rosasurfer\console\io\Input;


/**
 * HttpClient
 *
 * An HttpClient handling CURL requests for Dukascopy web resources. Performs login, authorization and session handling.
 */
class HttpClient extends CurlHttpClient {


    /**
     * Constructor
     *
     * Create a new instance.
     *
     * @param  array $options [optional] - additional options
     *                                     (default: none)
     */
    public function __construct(array $options = []) {
        $options[CURLOPT_SSL_VERIFYPEER] = false;               // suppress SSL certificate validation errors
        //$options[CURLOPT_VERBOSE     ] = true;
        parent::__construct($options);
    }


    /**
     * Download history start data for the specified symbol.
     *
     * @param  string $symbol [optional] - symbol (default: download data for all symbols)
     *
     * @return string - binary history start data or an empty string in case of errors
     */
    public function downloadHistoryStart($symbol = null) {
        if (isset($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
        if (strlen($symbol))
            $symbol .= '/';
        $symbol = strtoupper($symbol);
        $url = 'http://datafeed.dukascopy.com/datafeed/'.$symbol.'metadata/HistoryStart.bi5';

        $request = new DukascopyRequest($url);
        $response = $this->send($request);

        $status = $response->getStatus();
        if ($status!=HttpResponse::SC_OK && $status!=HttpResponse::SC_NOT_FOUND)
            throw new RuntimeException('Unexpected HTTP response status '.$status.' ('.HttpResponse::$sc[$status].')'.NL.'url: '.$url.NL.printPretty($response, true));

        // treat an empty response as 404 error (not found)
        $content = $response->getContent();
        if (!strlen($content))
            $status = HttpResponse::SC_NOT_FOUND;
        if ($status == HttpResponse::SC_NOT_FOUND) $this->di(Output::class)->stderr('[Error]   URL not found (404): '.$url);

        return ($status==HttpResponse::SC_OK) ? $response->getContent() : '';
    }


    /**
     * Download price history for the specified symbol.
     *
     * @param  string $symbol - symbol
     * @param  int    $day    - day
     * @param  int    $type   - price type
     *
     * @return string - binary history data or an empty string in case of errors
     */
    public function downloadHistory($symbol, $day, $type) {
        if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
        if (!strlen($symbol))    throw new InvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'"');
        if (!is_int($day))       throw new IllegalTypeException('Illegal type of parameter $day: '.gettype($day));
        if (!$day)               throw new InvalidArgumentException('Invalid parameter $day: '.$day);
        if (!is_int($type))      throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));
        if (!in_array($type, [PRICE_BID, PRICE_ASK]))
                                 throw new InvalidArgumentException('Invalid parameter $type: '.$type);

        /** @var Input $input */
        $input = $this->di(Input::class);
        /** @var Output $output */
        $output = $this->di(Output::class);

        // url: http://datafeed.dukascopy.com/datafeed/EURUSD/2009/00/31/BID_candles_min_1.bi5
        $symbolU = strtoupper($symbol);
        $yyyy = gmdate('Y', $day);
        $mm   = strRight((string)(gmdate('m', $day)+99), 2);        // months are zero-based: January = 00
        $dd   = gmdate('d', $day);
        $date = $yyyy.'/'.$mm.'/'.$dd;
        $name = ($type==PRICE_BID ? 'BID':'ASK').'_candles_min_1';
        $url  = 'http://datafeed.dukascopy.com/datafeed/'.$symbolU.'/'.$date.'/'.$name.'.bi5';

        if (!$input->getOption('--quiet')) {
            $output->out('[Info]    '.gmdate('D, d-M-Y', $day).'  downloading: '.$url);
        }

        $request = new DukascopyRequest($url);
        $response = $this->send($request);

        $status = $response->getStatus();
        if ($status!=HttpResponse::SC_OK && $status!=HttpResponse::SC_NOT_FOUND)
            throw new RuntimeException('Unexpected HTTP response status '.$status.' ('.HttpResponse::$sc[$status].')'.NL.'url: '.$url.NL.printPretty($response, true));

        // treat an empty response as 404 error (not found)
        $content = $response->getContent();
        if (!strlen($content))
            $status = HttpResponse::SC_NOT_FOUND;
        if ($status == HttpResponse::SC_NOT_FOUND) $output->error('[Error]   URL not found (404): '.$url);

        return ($status==HttpResponse::SC_OK) ? $response->getContent() : '';
    }
}
