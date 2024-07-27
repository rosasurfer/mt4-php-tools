<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\dukascopy;


/**
 * HttpRequest
 *
 * An HttpRequest for a Dukascopy web resource.
 */
class HttpRequest extends \rosasurfer\ministruts\net\http\HttpRequest {


    /**
     * Constructor
     *
     * Create a new HTTP request for a Dukascopy web resource.
     *
     * @param  string $url [optional] - url (default: none)
     */
    public function __construct($url = null) {
        parent::__construct($url);

        // emulate a standard web browser
        $this->setHeader('User-Agent'     ,  $this->di('config')['rt.http.useragent']                        )
             ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
             ->setHeader('Accept-Language', 'en-us'                                                          )
             ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
             ->setHeader('Connection'     , 'keep-alive'                                                     )
             ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
             ->setHeader('Referer'        , 'https://www.dukascopy.com/swiss/english/marketwatch/historical/');
    }
}
