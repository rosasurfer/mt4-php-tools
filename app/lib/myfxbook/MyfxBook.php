<?php
use rosasurfer\core\StaticClass;

use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\IOException;
use rosasurfer\exception\RuntimeException;


/**
 * MyfxBook related functionality
 */
class MyfxBook extends StaticClass {


   /**
    *
    */
   public static function loadCvsFile() {
      // Standard-Browser simulieren
      $userAgent = Config::getDefault()->get('myfx.useragent');
      if (!strLen($userAgent)) throw new InvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');

      $url     = '***REMOVED***';
      $referer = '***REMOVED***';
      $request = HttpRequest::create()
                            ->setUrl($url)
                            ->setHeader('User-Agent'     , $userAgent                                                       )
                            ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                            ->setHeader('Accept-Language', 'en-us'                                                          )
                            ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                            ->setHeader('Connection'     , 'keep-alive'                                                     )
                            ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
                            ->setHeader('Referer'        , $referer                                                         );

      // Cookies in der angegebenen Datei verwenden
      $cookieFile = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.'cookies.txt';
      $options[CURLOPT_COOKIEFILE] = $cookieFile;                    // read cookies from
      $options[CURLOPT_COOKIEJAR ] = $cookieFile;                    // write cookies to
      $options[CURLOPT_VERBOSE   ] = true;                           // enable debugging

      // HTTP-Request ausführen
      $client   = CurlHttpClient::create($options);
      $response = $client->send($request);
      $content  = $response->getContent();

      if (is_null($content))             throw new IOException('Empty reply from server, url: '.$request->getUrl());
      if ($response->getStatus() != 200) throw new RuntimeException('Unexpected HTTP status code '.($status=$response->getStatus()).' ('.HttpResponse ::$sc[$status].') for url: '.$request->getUrl());

      return $content;
   }
}
