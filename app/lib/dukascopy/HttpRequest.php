<?php
namespace rosasurfer\rt\dukascopy;


/**
 * HttpRequest
 *
 * An HttpRequest for a Dukascopy web resource.
 */
class HttpRequest extends \rosasurfer\net\http\HttpRequest {


    /**
     * Constructor
     *
     * Create a new HttpRequest.
     *
     * @param  string $url [optional] - url (default: none)
     */
    public function __construct($url = null) {
        parent::__construct($url);
    }

    //$request = (new HttpRequest($url))
    //           ->setHeader('User-Agent'     , $this->di()['config']['rt.http.useragent']                       )
    //           ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
    //           ->setHeader('Accept-Language', 'en-us'                                                          )
    //           ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
    //           ->setHeader('Connection'     , 'keep-alive'                                                     )
    //           ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
    //           ->setHeader('Referer'        , 'https://www.dukascopy.com/swiss/english/marketwatch/historical/');
}
