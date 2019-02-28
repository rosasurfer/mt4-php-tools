<?php
namespace rosasurfer\rt\lib\dukascopy;

use rosasurfer\console\io\Output;
use rosasurfer\exception\RuntimeException;
use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rt\lib\dukascopy\HttpRequest as DukascopyRequest;


/**
 * HttpClient
 *
 * An HttpClient handling cURL requests for Dukascopy web resources. Adds login, authorization and session handling.
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
        if (strlen($symbol))
            $symbol .= '/';
        $url = 'http://datafeed.dukascopy.com/datafeed/'.$symbol.'metadata/HistoryStart.bi5';

        $request  = new DukascopyRequest($url);
        $response = $this->send($request);
        $status   = $response->getStatus();
        if ($status!=200 && $status!=404) throw new RuntimeException('Unexpected HTTP status '.$status.' ('.HttpResponse::$sc[$status].') for url "'.$url.'"'.NL.printPretty($response, true));

        // treat an empty response as error 404
        $content = $response->getContent();
        if (!strlen($content))
            $status = 404;
        if ($status == 404) $this->di(Output::class)->stderr('[Error]   URL not found (404): '.$url);

        return ($status==200) ? $response->getContent() : '';
    }
}
