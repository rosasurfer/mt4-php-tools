<?php
namespace rosasurfer\myfx\lib\simpletrader;

use rosasurfer\config\Config;

use rosasurfer\core\StaticClass;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\IOException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\log\Logger;

use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpClient;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\util\Date;


/**
 * www.simpletrader.net related functionality
 */
class SimpleTrader extends StaticClass {


   /**
    * Die TTL der DNS-Einträge ist äußerst kurz:  60 Sekunden (03.09.2016)
    *
    *  $ dig cp.forexsignals.com a
    *  cp.forexsignals.com.    59      IN      CNAME   simpletrader.net.
    *  simpletrader.net.       59      IN      A       89.238.139.23
    *
    *
    *  $ dig www.simpletrader.net a
    *  www.simpletrader.net.   59      IN      CNAME   simpletrader.net.
    *  simpletrader.net.       59      IN      A       89.238.139.23
    */


   // URLs und Referers zum Download der letzten 500 Trades und der kompletten History
   private static $urls = [
      ['currentHistory' => 'http://cp.forexsignals.com/signal/{provider_signal_id}/signal.html',
       'fullHistory'    => 'http://cp.forexsignals.com/signals.php?do=view&id={provider_signal_id}&showAllClosedTrades=1',
       'referer'        => 'http://cp.forexsignals.com/forex-signals.html'],

      ['currentHistory' => 'https://www.simpletrader.net/signal/{provider_signal_id}/signal.html',
       'fullHistory'    => 'https://www.simpletrader.net/signals.php?do=view&id={provider_signal_id}&showAllClosedTrades=1',
       'referer'        => 'https://www.simpletrader.net/forex-signals.html'],
   ];


   /**
    * Lädt die HTML-Seite mit den Tradedaten des angegebenen Signals. Schlägt der Download fehl, wird zwei mal versucht,
    * die Seite von alternativen URL's zu laden, bevor der Download mit einem Fehler abbricht.
    *
    * @param  Signal $signal
    * @param  bool   $fullHistory - ob die komplette History oder nur die letzten Trades geladen werden sollen
    *
    * @return string - Inhalt der HTML-Seite
    */
   public static function loadSignalPage(\Signal $signal, $fullHistory) {
      if (!is_bool($fullHistory)) throw new IllegalTypeException('Illegal type of parameter $fullHistory: '.getType($fullHistory));

      $providerSignalId = $signal->getProviderID();


      // (1) Standard-Browser simulieren
      if (!$config=Config::getDefault()) throw new RuntimeException('Service locator returned invalid default config: '.getType($config));
      $userAgent = $config->get('myfx.useragent');
      if (!strLen($userAgent))           throw new InvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');
      $request = HttpRequest::create()
                            ->setHeader('User-Agent'     ,  $userAgent                                                      )
                            ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                            ->setHeader('Accept-Language', 'en-us'                                                          )
                            ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                            ->setHeader('Connection'     , 'keep-alive'                                                     )
                            ->setHeader('Cache-Control'  , 'max-age=0'                                                      );

      // Cookies in der angegebenen Datei verwenden
      $cookieFile = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.'cookies.txt';
      $options[CURLOPT_COOKIEFILE    ] = $cookieFile;                   // read cookies from
      $options[CURLOPT_COOKIEJAR     ] = $cookieFile;                   // write cookies to
      $options[CURLOPT_SSL_VERIFYPEER] = false;                         // das SimpleTrader-SSL-Zertifikat ist evt. ungültig


      // TODO: Bei einem Netzwerkausfall am Server muß das Script weiterlaufen und seine Arbeit bei Rückkehr des Netzwerkes fortsetzen.


      // (2) HTTP-Request ausführen
      $key     = $fullHistory ? 'fullHistory':'currentHistory';
      $counter = 0;
      while (true) {
         $i       = $counter % sizeof(self::$urls);                     // bei Neuversuchen abwechselnd alle URL's durchprobieren
         $url     = str_replace('{provider_signal_id}', $providerSignalId, self::$urls[$i][$key]);
         $referer = self::$urls[$i]['referer'];
         $request->setUrl($url)->setHeader('Referer', $referer);

         static $httpClient = null;                                     // Instanz wiederverwenden, um Keep-Alive-Connections zu ermöglichen
         !$httpClient && $httpClient=CurlHttpClient::create($options);

         try {
            $counter++;
            $response = $httpClient->send($request);
                                                                        // Serverfehler, entspricht CURLE_GOT_NOTHING
            if (is_null($response->getContent())) throw new IOException('Empty reply from server, url: '.$request->getUrl());
         }
         catch (IOException $ex) {
            $msg = $ex->getMessage();
            if (strStartsWith($msg, 'CURL error CURLE_COULDNT_RESOLVE_HOST') ||
                strStartsWith($msg, 'CURL error CURLE_COULDNT_CONNECT'     ) ||
                strStartsWith($msg, 'CURL error CURLE_OPERATION_TIMEDOUT'  ) ||
                strStartsWith($msg, 'CURL error CURLE_GOT_NOTHING'         ) ||
                strStartsWith($msg, 'Empty reply from server'              )) {
               if ($counter < 10) {                                     // bis zu 10 Versuche, eine URL zu laden
                  Logger::log($msg."\nretrying ... ($counter)", L_WARN);
                  sleep(10);                                            // vor jedem weiteren Versuch einige Sekunden warten
                  continue;
               }
            }
            throw $ex;
         }
         if (($status=$response->getStatus()) != 200) throw new RuntimeException('Unexpected HTTP status code '.$status.' ('.HttpResponse::$sc[$status].') for url: '.$request->getUrl());

         return $response->getContent();
      }
   }


   /**
    * Parst eine simpletrader.net HTML-Seite mit Signaldaten.
    *
    * @param  Signal  $signal     - Signal
    * @param  string  $html       - Inhalt der HTML-Seite
    * @param  array  &$openTrades - Array zur Aufnahme der offenen Positionen
    * @param  array  &$history    - Array zur Aufnahme der Signalhistory
    *
    * @return string - NULL oder Fehlermeldung, falls ein Fehler auftrat
    */
   public static function parseSignalData(\Signal $signal, &$html, array &$openTrades, array &$history) {      // der zusätzliche Zeiger minimiert den benötigten Speicher
      if (!is_string($html)) throw new IllegalTypeException('Illegal type of parameter $html: '.getType($html));

      $signalAlias = $signal->getAlias();

      // HTML-Entities konvertieren
      $html = str_replace('&nbsp;', ' ', $html);

      // ggf. RegExp-Stringlimit erhöhen
      if (strLen($html) > (int)ini_get('pcre.backtrack_limit'))
         PHP::ini_set('pcre.backtrack_limit', strLen($html));

      $matchedTables = $openTradeRows = $historyRows = $matchedOpenTrades = $matchedHistoryEntries = 0;
      $tables = [];

      // Tabellen <table id="openTrades"> und <table id="history"> extrahieren
      $matchedTables = preg_match_all('/<table\b.*\bid="(opentrades|history)".*>.*<tbody\b.*>(.*)<\/tbody>.*<\/table>/isU', $html, $tables, PREG_SET_ORDER);
      if ($matchedTables != 2) {
         // Login ungültig (falls Cookies ungültig oder korrupt sind)
         if (preg_match('/Please read the following information<\/h4>\s*(You do not have access to view this page\.)/isU', $html, $matches)) throw new RuntimeException($signal->getName().': '.$matches[1]);
         if (preg_match('/Please read the following information<\/h4>\s*(This signal does not exist\.)/isU'              , $html, $matches)) throw new RuntimeException($signal->getName().': '.$matches[1]);

         // diverse PHP-Fehler in der SimpleTrader-Website
         if (preg_match('/(Parse error: .+ in .+ on line [0-9]+)/iU', $html, $matches)) return $matches[1];    // Parse error: ... in /home/simpletrader/public_html/signals.php on line ...
         if (trim($html) == 'Database error...')                                        return trim($html);    // Database error...

         throw new RuntimeException($signal->getName().': tables "opentrades" and/or "history" not found, HTML:'.NL.NL.$html);
      }

      foreach ($tables as $i => &$table) {
         $table[0] = 'table '.($i+1);
         $table[1] = strToLower($table[1]);

         // offene Positionen extrahieren und parsen: Timezone = GMT
         if ($table[1] == 'opentrades') {                                        // [ 0:          ] => {matched html}
            /*                                                                   // [ 1:TakeProfit] => 1.319590
            <tr class="red topDir" title="Take Profit: 1.730990 Stop Loss: -">   // [ 2:StopLoss  ] => -                   // falls angegeben
               <td class="center">2014/09/08 13:25:42</td>                       // [ 3:OpenTime  ] => 2014/09/04 08:15:12
               <td class="center">1.294130</td>                                  // [ 4:OpenPrice ] => 1.314590
               <td class="center">1.24</td>                                      // [ 5:Lots      ] => 0.16
               <td class="center">Sell</td>                                      // [ 6:Type      ] => Buy
               <td class="center">EURUSD</td>                                    // [ 7:Symbol    ] => EURUSD
               <td class="center">-32.57</td>                                    // [ 8:Profit    ] => -281.42             // NetProfit
               <td class="center">-1.8</td>                                      // [ 9:Pips      ] => -226.9
               <td class="center">1999552</td>                                   // [10:Comment   ] => 2000641             // Ticket
            </tr>
            */
            $openTradeRows     = preg_match_all('/<tr\b/is', $table[2], $openTrades);
            $matchedOpenTrades = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $openTrades, PREG_SET_ORDER);
            if (!$openTradeRows) {
               if (preg_match('/"sEmptyTable": "No trades currently open/', $html)) {
                  // keine OpenTrades vorhanden
               }
               else if (preg_match('/"sEmptyTable": "(There are currently trades open[^"]*)"/', $html, $matches)) {
                  // OpenTrades sind gesperrt und können durch begonne, aber nicht abgeschlossene Subscription freigeschaltet werden.
                  throw new RuntimeException($signal->getName().': '.$matches[1]);
               }
               else throw new RuntimeException($signal->getName().': no open trade rows found, HTML:'.NL.NL.$html);
            }

            foreach ($openTrades as $i => &$row) {
               if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2]))) throw new RuntimeException('Error parsing TakeProfit or StopLoss in open position row '.($i+1).', HTML:'.NL.NL.$row[0]);

               // 0:
               //$row[0] = 'row '.($i+1);

               // 1:TakeProfit
               $sValue = trim($row[I_OPEN_TAKEPROFIT]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(double)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid TakeProfit found in open position row '.($i+1).': "'.$row[I_OPEN_TAKEPROFIT].'", HTML:'.NL.NL.$row[0]);
               $row['takeprofit'] = $dValue;

               // 2:StopLoss
               $sValue = trim($row[I_OPEN_STOPLOSS]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(double)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid StopLoss found in open position row '.($i+1).': "'.$row[I_OPEN_STOPLOSS].'", HTML:'.NL.NL.$row[0]);
               $row['stoploss'] = $dValue;

               // 3:OpenTime
               $sOpenTime = trim($row[I_OPEN_OPENTIME]);
               if (strEndsWith($sOpenTime, '*'))                     // seit 06.02.2015 von Fall zu Fall, Bedeutung ist noch unklar
                  $sOpenTime = subStr($sOpenTime, 0, -1);
               if (!($iTime=strToTime($sOpenTime.' GMT'))) throw new RuntimeException('Invalid OpenTime found in open position row '.($i+1).': "'.$row[I_OPEN_OPENTIME].'", HTML:'.NL.NL.$row[0]);
               $row['opentime' ] = $iTime;
               $row['closetime'] = null;

               // 4:OpenPrice
               $sValue = trim($row[I_OPEN_OPENPRICE]);
               if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Invalid OpenPrice found in open position row '.($i+1).': "'.$row[I_OPEN_OPENPRICE].'", HTML:'.NL.NL.$row[0]);
               $row['openprice' ] = $dValue;
               $row['closeprice'] = null;

               // 5:LotSize
               $sValue = trim($row[I_OPEN_LOTSIZE]);
               if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Invalid LotSize found in open position row '.($i+1).': "'.$row[I_OPEN_LOTSIZE].'", HTML:'.NL.NL.$row[0]);
               $row['lots'] = $dValue;

               // 6:Type
               $sValue = trim(strToLower($row[I_OPEN_TYPE]));
               // Bekannte Tickets mit fehlerhaften OperationType überspringen
               if ($sValue!='buy' && $sValue!='sell') {
                  $sTicket = trim($row[I_OPEN_COMMENT]);
                  if ($signalAlias=='novolr' && $sTicket=='3488580')          // permanente Fehler nicht jedesmal anzeigen
                     continue;
                  throw new RuntimeException('Invalid OperationType found in open position row '.($i+1).': "'.$row[I_OPEN_TYPE].'", HTML:'.NL.NL.$row[0]);
               }
               $row['type'] = $sValue;

               // 7:Symbol
               $sValue = trim($row[I_OPEN_SYMBOL]);
               if (empty($sValue)) throw new RuntimeException('Invalid Symbol found in open position row '.($i+1).': "'.$row[I_OPEN_SYMBOL].'", HTML:'.NL.NL.$row[0]);
               $row['symbol'] = $sValue;

               // 8:Profit
               $sValue = trim($row[I_OPEN_PROFIT]);
               if (!is_numeric($sValue)) throw new RuntimeException('Invalid Profit found in open position row '.($i+1).': "'.$row[I_OPEN_PROFIT].'", HTML:'.NL.NL.$row[0]);
               $row['commission' ] = null;
               $row['swap'       ] = null;
               $row['grossprofit'] = null;
               $row['netprofit'  ] = (double)$sValue;

               // 9:Pips

               // 10:Comment
               $sValue = trim($row[I_OPEN_COMMENT]);
               if (!ctype_digit($sValue)) throw new RuntimeException('Invalid Comment found in open position row '.($i+1).': "'.$row[I_OPEN_COMMENT].'" (non-digits), HTML:'.NL.NL.$row[0]);
               $row['ticket'] = (int)$sValue;

               unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10]);
            } unset($row);

            // offene Positionen sortieren: ORDER BY OpenTime asc, Ticket asc
            uSort($openTrades, __CLASS__.'::compareTradesByOpenTimeTicket');
         }

         // History extrahieren und parsen: Timezone = GMT
         if ($table[1] == 'history') {                                        // [ 0:          ] => {matched html}
            /*                                                                // [ 1:TakeProfit] =>                        // falls angegeben
            <tr class="green even">                                           // [ 2:StopLoss  ] =>                        // falls angegeben
               <td class="center">2015/01/14 14:07:06</td>                    // [ 3:OpenTime  ] => 2014/09/09 13:05:58
               <td class="center">2015/01/14 14:12:00</td>                    // [ 4:CloseTime ] => 2014/09/09 13:08:15
               <td class="center">1.183290</td>                               // [ 5:OpenPrice ] => 1.742870
               <td class="center">1.182420</td>                               // [ 6:ClosePrice] => 1.743470
               <td class="center">1.60</td>                                   // [ 7:Lots      ] => 0.12
               <td class="center">Sell</td>                                   // [ 8:Type      ] => Sell
               <td class="center">EURUSD</td>                                 // [ 9:Symbol    ] => GBPAUD
               <td class="center">126.40</td>                                 // [10:Profit    ] => -7.84                  // NetProfit
               <td class="center">8.7</td>                                    // [11:Pips      ] => -6
               <td class="center">0.07%</td>                                  // [12:Gain      ] => -0.07%
               <td class="center">2289768</td>                                // [13:Comment   ] => 2002156                // Ticket
            </tr>
            */
            $historyRows           = preg_match_all('/<tr\b/is', $table[2], $history);
            $matchedHistoryEntries = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $history, PREG_SET_ORDER);

            if (!$historyRows && !preg_match('/"sEmptyTable": "There is currently no history/', $html))
               throw new RuntimeException($signal->getName().': no history rows found, HTML:'.NL.NL.$table[2]);

            foreach ($history as $i => &$row) {
               if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2]))) throw new RuntimeException('Error parsing TakeProfit or StopLoss in history row '.($i+1).', HTML:'.NL.NL.$row[0]);

               // 0:
               //$row[0] = 'row '.($i+1);

               // 1:TakeProfit
               $sValue = trim($row[I_HISTORY_TAKEPROFIT]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(double)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid TakeProfit found in history row '.($i+1).': "'.$row[I_HISTORY_TAKEPROFIT].'", HTML:'.NL.NL.$row[0]);
               $row['takeprofit'] = $dValue;

               // 2:StopLoss
               $sValue = trim($row[I_HISTORY_STOPLOSS]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(double)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid StopLoss found in history row '.($i+1).': "'.$row[I_HISTORY_STOPLOSS].'", HTML:'.NL.NL.$row[0]);
               $row['stoploss'] = $dValue;

               // 3:OpenTime
               $sOpenTime = trim($row[I_HISTORY_OPENTIME]);
               if (strEndsWith($sOpenTime, '*'))                              // seit 06.02.2015 von Fall zu Fall, Bedeutung noch unklar
                  $sOpenTime = subStr($sOpenTime, 0, -1);
               if (!($iTime=strToTime($sOpenTime.' GMT'))) throw new RuntimeException('Invalid OpenTime found in history row '.($i+1).': "'.$row[I_HISTORY_OPENTIME].'", HTML:'.NL.NL.$row[0]);
               $row['opentime'] = $iTime;

               // 4:CloseTime
               $sCloseTime = trim($row[I_HISTORY_CLOSETIME]);
               if (!($iTime=strToTime($sCloseTime.' GMT'))) throw new RuntimeException('Invalid CloseTime found in history row '.($i+1).': "'.$row[I_HISTORY_CLOSETIME].'", HTML:'.NL.NL.$row[0]);
               // Tickets mit fehlerhaften Open-/CloseTimes überspringen
               if ($row['opentime'] > $iTime) {
                  $sTicket = trim($row[I_HISTORY_COMMENT]);
                  $row     = [];
                  if ($signalAlias=='smarttrader' && $sTicket=='1175928') {}  // permanente Fehler nicht jedesmal anzeigen
                  else echoPre('Skipping invalid ticket #'.$sTicket.': OpenTime='.$sOpenTime.'  CloseTime='.$sCloseTime);
                  continue;
               }
               $row['closetime'] = $iTime;

               // 5:OpenPrice
               $sValue = trim($row[I_HISTORY_OPENPRICE]);
               if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Invalid OpenPrice found in history row '.($i+1).': "'.$row[I_HISTORY_OPENPRICE].'", HTML:'.NL.NL.$row[0]);
               $row['openprice'] = $dValue;

               // 6:ClosePrice
               $sValue = trim($row[I_HISTORY_CLOSEPRICE]);
               if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Invalid ClosePrice found in history row '.($i+1).': "'.$row[I_HISTORY_CLOSEPRICE].'", HTML:'.NL.NL.$row[0]);
               $row['closeprice'] = $dValue;

               // 7:LotSize
               $sValue = trim($row[I_HISTORY_LOTSIZE]);
               if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Invalid LotSize found in history row '.($i+1).': "'.$row[I_HISTORY_LOTSIZE].'", HTML:'.NL.NL.$row[0]);
               $row['lots'] = $dValue;

               // 8:Type
               $sValue = trim(strToLower($row[I_HISTORY_TYPE]));
               // Tickets mit fehlerhaften OperationType reparieren
               if ($sValue!='buy' && $sValue!='sell') {
                  if (is_numeric($sProfit=trim($row[I_HISTORY_PROFIT])) && $row['openprice'] != $row['closeprice']) {
                     $dProfit = (double)$sProfit;
                     if ($row['openprice'] < $row['closeprice']) $fixedValue = ($dProfit > 0) ? 'Buy' :'Sell';
                     else                                        $fixedValue = ($dProfit > 0) ? 'Sell':'Buy' ;
                     echoPre('Fixing invalid operation type "'.$sValue.'" of ticket #'.$sTicket.': '.$fixedValue);
                     $sValue = strToLower($fixedValue);
                  }
                  else throw new RuntimeException('Invalid OperationType found in history row '.($i+1).': "'.$row[I_HISTORY_TYPE].'", HTML:'.NL.NL.$row[0]);
               }
               $row['type'] = $sValue;

               // 9:Symbol
               $sValue = trim($row[I_HISTORY_SYMBOL]);
               if (empty($sValue)) throw new RuntimeException('Invalid Symbol found in history row '.($i+1).': "'.$row[I_HISTORY_SYMBOL].'", HTML:'.NL.NL.$row[0]);
               $row['symbol'] = $sValue;

               // 10:Profit
               $sValue = trim($row[I_HISTORY_PROFIT]);
               if (!is_numeric($sValue)) throw new RuntimeException('Invalid Profit found in history row '.($i+1).': "'.$row[I_HISTORY_PROFIT].'", HTML:'.NL.NL.$row[0]);
               $row['commission' ] = null;
               $row['swap'       ] = null;
               $row['grossprofit'] = null;
               $row['netprofit'  ] = (double)$sValue;

               // 11:Pips

               // 12:Gain

               // 13:Comment
               $sValue = trim($row[I_HISTORY_COMMENT]);
               if (!ctype_digit($sValue)) throw new RuntimeException('Invalid Comment found in history row '.($i+1).': "'.$row[I_HISTORY_COMMENT].'" (non-digits), HTML:'.NL.NL.$row[0]);
               $row['ticket'] = (int)$sValue;

               unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12], $row[13]);
            } unset($row);

            // History sortieren: ORDER BY CloseTime asc, OpenTime asc, Ticket asc
            uSort($history, __CLASS__.'::compareTradesByCloseTimeOpenTimeTicket');
         }
      } unset($table);

      if ($openTradeRows != $matchedOpenTrades    ) throw new RuntimeException('Could not match '.($openTradeRows-$matchedOpenTrades  ).' row'.pluralize($openTradeRows-$matchedOpenTrades));
      if ($historyRows   != $matchedHistoryEntries) throw new RuntimeException('Could not match '.($historyRows-$matchedHistoryEntries).' row'.pluralize($historyRows-$matchedHistoryEntries));

      return null;
   }


   /**
    * Comparator, der zwei Trades zunächst anhand ihrer OpenTime vergleicht. Ist die OpenTime gleich,
    * werden die Trades anhand ihres Tickets verglichen.
    *
    * @param  array $tradeA
    * @param  array $tradeB
    *
    * @return int - positiver Wert, wenn $tradeA nach $tradeB geöffnet wurde;
    *               negativer Wert, wenn $tradeA vor $tradeB geöffnet wurde;
    *               0, wenn beide Trades zum selben Zeitpunkt geöffnet wurden
    */
   private static function compareTradesByOpenTimeTicket(array $tradeA, array $tradeB) {
      if (!$tradeA) return $tradeB ? -1 : 0;
      if (!$tradeB) return $tradeA ?  1 : 0;

      if ($tradeA['opentime'] > $tradeB['opentime']) return  1;
      if ($tradeA['opentime'] < $tradeB['opentime']) return -1;

      if ($tradeA['ticket'  ] > $tradeB['ticket'  ]) return  1;
      if ($tradeA['ticket'  ] < $tradeB['ticket'  ]) return -1;

      return 0;
   }


   /**
    * Comparator, der zwei Trades zunächst anhand ihrer CloseTime vergleicht. Ist die CloseTime gleich, werden die Trades
    * anhand ihrer OpenTime verglichen. Ist auch die OpenTime gleich, werden die Trades anhand ihres Tickets verglichen.
    *
    * @param  array $tradeA
    * @param  array $tradeB
    *
    * @return int - positiver Wert, wenn $tradeA nach $tradeB geschlossen wurde;
    *               negativer Wert, wenn $tradeA vor $tradeB geschlossen wurde;
    *               0, wenn beide Trades zum selben Zeitpunkt geöffnet und geschlossen wurden
    */
   private static function compareTradesByCloseTimeOpenTimeTicket(array $tradeA, array $tradeB) {
      if (!$tradeA) return $tradeB ? -1 : 0;
      if (!$tradeB) return $tradeA ?  1 : 0;

      if ($tradeA['closetime'] > $tradeB['closetime']) return  1;
      if ($tradeA['closetime'] < $tradeB['closetime']) return -1;
      return self::compareTradesByOpenTimeTicket($tradeA, $tradeB);
   }


   /**
    * Handler für PositionOpen-Events eines SimpleTrader-Signals.
    *
    * @param  OpenPosition $position - die geöffnete Position
    */
   public static function onPositionOpen(\OpenPosition $position) {
      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = $signal->getName().' opened '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().'  TP: '.ifNull($position->getTakeProfit(),'-').'  SL: '.ifNull($position->getStopLoss(), '-').'  ('.$position->getOpenTime('H:i:s').')';
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      try {
         $mailMsg = $signal->getName().' Open '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice();
         foreach (\MyFX::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
         }
      }
      catch (\Exception $ex) { Logger::log($ex, L_ERROR); }


      // SMS-Benachrichtigung, wenn das Ereignis zur Laufzeit des Scriptes eintrat
      $openTime = \MyFX::fxtStrToTime($position->getOpenTime());
      if ($openTime >= $_SERVER['REQUEST_TIME']) {
         try {
            $smsMsg = 'Opened '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().(($tp=$position->getTakeProfit()) ? "\nTP: $tp":'').(($sl=$position->getStopLoss()) ? ($tp ? '  ':"\n")."SL: $sl":'')."\n\n#".$position->getTicket().'  ('.$position->getOpenTime('H:i:s').')';

            // Warnung, wenn das Ereignis älter als 2 Minuten ist (also von SimpleTrader verzögert publiziert wurde)
            if (($now=time()) > $openTime+2*MINUTES) $smsMsg = 'WARN: '.$smsMsg.' detected at '.date($now); // MyFX::fxtDate($now)

            foreach (\MyFX::getSmsSignalReceivers() as $receiver) {
               \MyFX::sendSms($receiver, $smsMsg);
            }
         }
         catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
      }
   }


   /**
    * Handler für PositionModify-Events eines SimpleTrader-Signals.
    *
    * @param  OpenPosition $position - die modifizierte Position (nach der Änderung)
    * @param  double       $prevTP   - der vorherige TakeProfit-Wert
    * @param  double       $prevSL   - der vorherige StopLoss-Wert
    */
   public static function onPositionModify(\OpenPosition $position, $prevTP, $prevSL) {
      if (!is_null($prevTP) && !is_float($prevTP)) throw new IllegalTypeException('Illegal type of parameter $prevTP: '.getType($prevSL));
      if (!is_null($prevSL) && !is_float($prevSL)) throw new IllegalTypeException('Illegal type of parameter $prevSL: '.getType($prevSL));

      $modification = $tpMsg = $slMsg = null;
      if (($current=$position->getTakeprofit()) != $prevTP) $modification .= ($tpMsg=' TP: '.($prevTP ? $prevTP.' => ':'').($current ? $current:'-'));
      if (($current=$position->getStopLoss())   != $prevSL) $modification .= ($slMsg=' SL: '.($prevSL ? $prevSL.' => ':'').($current ? $current:'-'));
      if (!$modification) throw new RuntimeException('No modification found in OpenPosition '.$position);

      $signal = $position->getSignal();

      // Ausgabe in Console
      $format = "%-4s %4.2F %s @ %-8s";
      $type   = $position->getType();
      $lots   = $position->getLots();
      $symbol = $position->getSymbol();
      $price  = $position->getOpenPrice();
      $msg    = sprintf($format, ucFirst($type), $lots, $symbol, $price);
      echoPre(date('Y-m-d H:i:s', time()).':  modify '.$msg.$modification);

      // Benachrichtigung per E-Mail
      $mailMsg = $signal->getName().': modify '.$msg.$modification;
      try {
         foreach (\MyFX::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject=$mailMsg, $mailMsg);
         }
      }
      catch (\Exception $ex) { Logger::log($ex, L_ERROR); }


      // Benachrichtigung per SMS
      if (false) {                                                   // für Limitänderungen vorerst deaktiviert
         try {
            $smsMsg = $signal->getName().': modified '.str_replace('  ', ' ', $msg)."\n"
                     .$modification                                                ."\n"
                     .date('(H:i:s)', time());                       // MyFX ::fxtDate(time(), '(H:i:s)')
            foreach (\MyFX::getSmsSignalReceivers() as $receiver) {
               \MyFX::sendSms($receiver, $smsMsg);
            }
         }
         catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
      }
   }


   /**
    * Handler für PositionClose-Events eines SimpleTrader-Signals.
    *
    * @param  ClosedPosition $position - die geschlossene Position
    */
   public static function onPositionClose(\ClosedPosition $position) {
      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = $signal->getName().' closed '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().'  Open: '.$position->getOpenPrice().'  Close: '.$position->getClosePrice().'  Profit: '.$position->getNetProfit(2).'  ('.$position->getCloseTime('H:i:s').')';
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      try {
         $mailMsg = $signal->getName().' Close '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice();
         foreach (\MyFX::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
         }
      }
      catch (\Exception $ex) { Logger::log($ex, L_ERROR); }


      // SMS-Benachrichtigung, wenn das Ereignis zur Laufzeit des Scriptes eintrat
      $closeTime = \MyFX::fxtStrToTime($position->getCloseTime());
      if ($closeTime >= $_SERVER['REQUEST_TIME']) {
         try {
            $smsMsg = 'Closed '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice()."\nOpen: ".$position->getOpenPrice()."\n\n#".$position->getTicket().'  ('.$position->getCloseTime('H:i:s').')';

            // Warnung, wenn das Ereignis älter als 2 Minuten ist (also von SimpleTrader verzögert publiziert wurde)
            if (($now=time()) > $closeTime+2*MINUTES) $smsMsg = 'WARN: '.$smsMsg.' detected at '.date($now);      // MyFX ::fxtDate($now)

            foreach (\MyFX::getSmsSignalReceivers() as $receiver) {
               \MyFX::sendSms($receiver, $smsMsg);
            }
         }
         catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
      }
   }


   /**
    * Handler für PositionChange-Events eines SimpleTrader-Signals.
    */
   public static function onPositionChange(\Signal $signal, $symbol, array $report, $iFirstNewRow, $oldNetPosition, $newNetPosition) {
      if (!$signal->isPersistent())    throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
      if (!is_string($symbol))         throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!strLen($symbol))            throw new InvalidArgumentException('Invalid argument $symbol: '.$symbol);
      if (!is_int($iFirstNewRow))      throw new IllegalTypeException('Illegal type of parameter $iFirstNewRow: '.getType($iFirstNewRow));
      if ($iFirstNewRow < 0)           throw new InvalidArgumentException('Invalid argument $iFirstNewRow: '.$iFirstNewRow);
      $rows = sizeOf($report);
      if ($iFirstNewRow >= $rows)      throw new InvalidArgumentException('Invalid argument $iFirstNewRow: '.$iFirstNewRow);
      $i = $iFirstNewRow;
      if (!is_string($oldNetPosition)) throw new IllegalTypeException('Illegal type of parameter $oldNetPosition: '.getType($oldNetPosition));
      if (!strLen($oldNetPosition))    throw new InvalidArgumentException('Invalid argument $oldNetPosition: '.$oldNetPosition);
      if (!is_string($newNetPosition)) throw new IllegalTypeException('Illegal type of parameter $newNetPosition: '.getType($newNetPosition));
      if (!strLen($newNetPosition))    throw new InvalidArgumentException('Invalid argument $newNetPosition: '.$newNetPosition);

      $lastTradeTime = \MyFX::fxtStrToTime($report[$rows-1]['time']);

      $msg = $signal->getName().': ';
      if ($i < $rows-1) $msg .= ($rows-$i).' trades in '.$symbol;
      else              $msg .= ($report[$i]['trade']=='open' ? '' : $report[$i]['trade'].' ').ucFirst($report[$i]['type']).' '.number_format($report[$i]['lots'], 2).' '.$symbol.' @ '.$report[$i]['price'];

      $subject = $msg;
      $msg .= "\nwas: ".str_replace('  ', ' ', $oldNetPosition);
      $msg .= "\nnow: ".str_replace('  ', ' ', $newNetPosition);
      $msg .= "\n".date('(H:i:s)', $lastTradeTime);               // MyFX ::fxtDate($lastTradeTime, '(H:i:s)')


      // Benachrichtigung per E-Mail
      try {
         foreach (\MyFX::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject, $msg);
         }
      }
      catch (\Exception $ex) { Logger::log($ex, L_ERROR); }


      // Benachrichtigung per SMS, wenn das Event zur Laufzeit des Scriptes eintrat
      if ($lastTradeTime >= $_SERVER['REQUEST_TIME']) {
         try {
            // Warnung, wenn der letzte Trade älter als 2 Minuten ist (von SimpleTrader also verzögert publiziert wurde)
            if (($now=time()) > $lastTradeTime+2*MINUTES) $msg = 'WARN: '.$msg.', detected at '.date('H:i:s', $now);    // MyFX ::fxtDate($now, 'H:i:s')

            foreach (\MyFX::getSmsSignalReceivers() as $receiver) {
               \MyFX::sendSms($receiver, $msg);
            }
         }
         catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
      }
   }
}
