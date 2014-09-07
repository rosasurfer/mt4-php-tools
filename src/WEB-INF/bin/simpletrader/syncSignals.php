#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die lokalen Daten mit denen des angegebenen Signals.  Die lokalen Daten können sich in einer Datenbank
 * oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt und eine Mail oder SMS
 * verschickt werden.
 */
require(dirName(__FILE__).'/../config.php');


// Daten der zur Zeit unterstützten Signale
$signals = array('alexprofit'   => array('id'=>2474, 'name'=>'AlexProfit'  ),
                 'dayfox'       => array('id'=>2465, 'name'=>'DayFox'      ),
                 'smarttrader'  => array('id'=>1081, 'name'=>'SmartTrader' ),
                 'smartscalper' => array('id'=>1086, 'name'=>'SmartScalper'),
                 );


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenparameter einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
(sizeOf($args)!=1 || in_array(strToLower($args[0]), array('-?','/?','-h','/h','-help','/help'))) && exit(1|help());
(!array_key_exists(strToLower($args[0]), $signals))                                              && exit(1|help('Unknown signal: '.$args[0]));


// Signal verarbeiten
processSignal($args[0]);
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 *
 * @param  string $signal - Signal-Name
 */
function processSignal($signal) {
   // Parametervalidierung
   if (!is_string($signal)) throw new IllegalTypeException('Illegal type of parameter $signal: '.getType($signal));
   $signal = strToLower($signal);

   global $signals;
   $signalID   = $signals[$signal]['id'  ];
   $signalName = $signals[$signal]['name'];

   echoPre('syncing signal '.$signalName.'...');
   /**
    * URL:    http://cp.forexsignals.com/signal/{signal_id}/signal.html                               (mit und ohne SSL)
    * Cookie: email=address@domain.tld; session=***REMOVED***
    *
    * URL:    https://www.simpletrader.net/signal/{signal_id}/signal.html                             (nur mit SSL)
    * Cookie: email=address@domain.tld; session=***REMOVED***
    */

   // GET /signal/2465/signal.html HTTP/1.1
   // Host:            cp.forexsignals.com
   // User-Agent:      ***REMOVED***
   // Accept:          text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
   // Accept-Language: en-us
   // Accept-Charset:  ISO-8859-1,utf-8;q=0.7,*;q=0.7
   // Accept-Encoding: gzip, deflate
   // Keep-Alive:      115
   // Connection:      keep-alive
   // Referer:         http://cp.forexsignals.com/forex-signals.html
   // Cookie:          email=address@domain.tld; session=***REMOVED***


   // HTTP-Request definieren
   $request = HttpRequest ::create()
                          ->setUrl('http://cp.forexsignals.com/signal/'.$signalID.'/signal.html')

                          ->setHeader('User-Agent'     , '***REMOVED***')
                          ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                          ->setHeader('Accept-Language', 'en-us')
                          ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7')
                          ->setHeader('Keep-Alive'     , '115')
                          ->setHeader('Connection'     , 'keep-alive');

   // Cookies in der angegebenen Datei verwenden/speichern
   $cookieStore = dirName($_SERVER['PHP_SELF']).DIRECTORY_SEPARATOR.'cookies.txt';
   $options = array(CURLOPT_COOKIEFILE => $cookieStore,     // The name of a file containing cookie data to use for the request.
                    CURLOPT_COOKIEJAR  => $cookieStore);    // The name of a file to save cookie data to when the connection closes.

   // HTTP-Request ausführen
   $response = CurlHttpClient ::create($options)->send($request);
   $status   = $response->getStatus();
   $content  = $response->getContent();
   if ($status != 200) throw new plRuntimeException('Unexpected HTTP status code from cp.forexsignals.com: '.$status.' ('.HttpResponse ::$sc[$status].')');

   // Antwort auswerten
   echoPre($content);
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n");
   global $signals;
   echo("\n  Syntax: ".baseName($_SERVER['PHP_SELF'])."  [".implode('|', array_keys($signals))."]\n");
}
?>
