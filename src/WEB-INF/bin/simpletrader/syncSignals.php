#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die lokalen Daten mit denen des angegebenen Signals.  Die lokalen Daten können sich in einer Datenbank
 * oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt und eine Mail oder SMS
 * verschickt werden.
 */
require(dirName(__FILE__).'/../config.php');


// zur Zeit unterstützte Signale
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

   echoPre('Syncing signal '.$signalName.'...');

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
   if (true) {
      $response = CurlHttpClient ::create($options)->send($request);
      $status   = $response->getStatus();
      $content  = $response->getContent();
      if ($status != 200) throw new plRuntimeException('Unexpected HTTP status code from cp.forexsignals.com: '.$status.' ('.HttpResponse ::$sc[$status].')');
   }
   else {
      $filename = dirName($_SERVER['PHP_SELF']).DIRECTORY_SEPARATOR.$signal.'.html';
      $content  = file_get_contents($filename, false);
   }

   // Antwort auswerten
   parseHtml($content);
}


/**
 * Parst den geladenen HTML-Content.
 *
 * @param  string $html - HTML-String
 */
function parseHtml(&$html) {
   // ggf. RegExp-Stringlimit erhöhen
   $html = str_replace('&nbsp;', ' ', $html);
   if (strLen($html) > (int)ini_get('pcre.backtrack_limit'))
      ini_set('pcre.backtrack_limit', strLen($html));


   $matchedTables = $openTradeRows = $closedTradeRows = $matchedOpenTrades = $matchedClosedTrades = 0;
   $tables        = $openTrades    = $closedTrades    = array();


   // Tabellen <table id="openTrades"> und <table id="history"> extrahieren
   $matchedTables = preg_match_all('/<table\b.*\bid="(opentrades|history)".*>.*<tbody\b.*>(.*)<\/tbody>.*<\/table>/isU', $html, $tables, PREG_SET_ORDER);
   foreach ($tables as $i => &$table) {
      $table[0] = 'table '.($i+1);
      $table[1] = strToLower($table[1]);

      // offene Positionen extrahieren
      if ($table[1] == 'opentrades') {
         /*
         <tr class="red topDir" title="Take Profit: 1.730990 Stop Loss: -">
            <td class="center">2014/09/08 13:25:42</td>
            <td class="center">1.294130</td>
            <td class="center">1.24</td>
            <td class="center">Sell</td>
            <td class="center">EURUSD</td>
            <td class="center">-32.57</td>
            <td class="center">-1.8</td>
            <td class="center">1999552</td>
         </tr>

         Array(
            [ 0:          ] => {matched html}
            [ 1:TakeProfit] => 1.319590
            [ 2:StopLoss  ] => -
            [ 3:OpenTime  ] => 2014/09/04 08:15:12
            [ 4:OpenPrice ] => 1.314590
            [ 5:Lots      ] => 0.16
            [ 6:Type      ] => Buy
            [ 7:Symbol    ] => EURUSD
            [ 8:Profit    ] => -281.42
            [ 9:Pips      ] => -226.9
            [10:Comment   ] => 2000641
         )
         */
         $openTradeRows     = preg_match_all('/<tr\b/is', $table[2], $openTrades);
         $matchedOpenTrades = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $openTrades, PREG_SET_ORDER);
         foreach ($openTrades as $i => &$row) {
            if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2])))
               throw new plRuntimeException('Error parsing TakeProfit or StopLoss in open position row '.($i+1).":\n".$row[0]);
            $row[0] = 'row '.($i+1);
         }
      }

      // History extrahieren
      if ($table[1] == 'history') {
         /*
         <tr class="green">                              // Sollten TP oder SL angegeben sein, werden sie auch hier erkannt.
            <td class="center">2014/09/05 16:32:52</td>
            <td class="center">2014/09/08 04:57:25</td>
            <td class="center">1.294620</td>
            <td class="center">1.294130</td>
            <td class="center">1.24</td>
            <td class="center">Sell</td>
            <td class="center">EURUSD</td>
            <td class="center">47.43</td>
            <td class="center">4.9</td>
            <td class="center">1996607</td>
         </tr>

         Array(
            [ 0:          ] => {matched html}
            [ 1:TakeProfit] =>
            [ 2:StopLoss  ] =>
            [ 3:OpenTime  ] => 2014/09/09 13:05:58
            [ 4:CloseTime ] => 2014/09/09 13:08:15
            [ 5:OpenPrice ] => 1.742870
            [ 6:ClosePrice] => 1.743470
            [ 7:Lots      ] => 0.12
            [ 8:Type      ] => Sell
            [ 9:Symbol    ] => GBPAUD
            [10:Profit    ] => -7.84
            [11:Pips      ] => -6
            [12:Comment   ] => 2002156
         )
         */
         $closedTradeRows     = preg_match_all('/<tr\b/is', $table[2], $closedTrades);
         $matchedClosedTrades = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $closedTrades, PREG_SET_ORDER);
         foreach ($closedTrades as $i => &$row) {
            if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2])))
               throw new plRuntimeException('Error parsing TakeProfit or StopLoss in closed position row '.($i+1).":\n".$row[0]);
            $row[0] = 'row '.($i+1);
         }
      }
   }
   //echoPre($tables);
   //echoPre($matchedTables      .' table'       .($foundTables      ==1 ? '':'s'));


   // Anzeige $openTrades
   foreach ($openTrades as $i => $openTrade) {
      if ($i >= 0) break;
      echoPre($openTrade);
   }
   echoPre($matchedOpenTrades  .' open trade'  .($matchedOpenTrades  ==1 ? '':'s').($openTradeRows  ==$matchedOpenTrades   ? '':' (could not match '.($openTradeRows  -$matchedOpenTrades)  .' row'.($openTradeRows-$matchedOpenTrades    ==1 ? '':'s').')'));


   // Anzeige $closedTrades
   foreach ($closedTrades as $i => $closedTrade) {
      if ($i >= 0) break;
      echoPre($closedTrade);
   }
   echoPre($matchedClosedTrades.' closed trade'.($matchedClosedTrades==1 ? '':'s').($closedTradeRows==$matchedClosedTrades ? '':' (could not match '.($closedTradeRows-$matchedClosedTrades).' row'.($closedTradeRows-$matchedClosedTrades==1 ? '':'s').')'));
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n");
   global $signals;
   echo("\n  Syntax: ".baseName($_SERVER['PHP_SELF'])."  [".implode('|', array_keys($signals))."]\n");
}
?>
