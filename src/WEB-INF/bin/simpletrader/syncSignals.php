#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die lokalen Daten mit denen der angegebenen Signale.  Die lokalen Daten können sich in einer Datenbank
 * oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt und eine Mail oder SMS
 * verschickt werden.
 */
require(dirName(realPath(__FILE__)).'/../config.php');


// zur Zeit unterstützte Signale
$signals = array('alexprofit'   => array('id'   => 2474,
                                         'name' => 'AlexProfit',
                                       //'url'  => 'http://cp.forexsignals.com/signal/2474/signal.html'),   // ohne SSL: komprimiert
                                         'url'  => 'https://www.simpletrader.net/signal/2474/signal.html'), //  mit SSL: nicht komprimiert

                 'dayfox'       => array('id'   => 2465,
                                         'name' => 'DayFox',
                                         'url'  => 'http://cp.forexsignals.com/signal/2465/signal.html'),

                 'goldstar'     => array('id'   => 2622,
                                         'name' => 'GoldStar',
                                         'url'  => 'http://cp.forexsignals.com/signal/2622/signal.html'),

                 'smarttrader'  => array('id'   => 1081,
                                         'name' => 'SmartTrader',
                                         'url'  => 'http://cp.forexsignals.com/signal/1081/signal.html'),

                 'smartscalper' => array('id'   => 1086,
                                         'name' => 'SmartScalper',
                                         'url'  => 'http://cp.forexsignals.com/signal/1086/signal.html'),
                 );


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenparameter einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
!$args                                                        && exit(1|help());
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   in_array($arg, array('-?','/?','-h','/h','-help','/help')) && exit(1|help());
   !array_key_exists($arg, $signals)                          && exit(1|help('Unknown signal: '.$args[$i]));
   $args[$i] = $arg;
}
$args = array_unique($args);


// Signale verarbeiten
foreach ($args as $i => $arg) {
   processSignal($arg);
}
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
   $signalUrl  = $signals[$signal]['url' ];

   echoPre("\nSyncing signal $signalName...");

   /**
    * URL:    http://cp.forexsignals.com/signal/{signal_id}/signal.html                               (mit und ohne SSL)
    * Cookie: email=address@domain.tld; session=***REMOVED***               (ohne SSL komprimiert)
    *
    * URL:    https://www.simpletrader.net/signal/{signal_id}/signal.html                             (nur mit SSL)
    * Cookie: email=address@domain.tld; session=***REMOVED***    (nicht komprimiert)
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

   // HTTP-Request definieren und Browser simulieren
   $request = HttpRequest ::create()
                          ->setUrl($signalUrl)
                          ->setHeader('User-Agent'     , '***REMOVED***')
                          ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                          ->setHeader('Accept-Language', 'en-us')
                          ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7')
                          ->setHeader('Keep-Alive'     , '115')
                          ->setHeader('Connection'     , 'keep-alive');

   // Cookies in der angegebenen Datei verwenden/speichern
   $cookieStore = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.'cookies.txt';
   $options = array(CURLOPT_COOKIEFILE => $cookieStore,     // The name of a file containing cookie data to use for the request.
                    CURLOPT_COOKIEJAR  => $cookieStore);    // The name of a file to save cookie data to when the connection closes.

   // HTTP-Request ausführen
   if (true) {
      $options[CURLOPT_SSL_VERIFYPEER] = false;             // das SSL-Zertifikat von www.simpletrader.net ist u.U. ungültig

      $response = CurlHttpClient ::create($options)->send($request);
      $status   = $response->getStatus();
      $content  = $response->getContent();
      if ($status != 200) throw new plRuntimeException('Unexpected HTTP status code from cp.forexsignals.com: '.$status.' ('.HttpResponse ::$sc[$status].')');
   }
   else {
      $filename = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.$signal.'.html';
      $content  = file_get_contents($filename, false);
   }

   // Antwort parsen
   $openPositions = $history = array();
   parseHtml($signal, $content, $openPositions, $history);

   // offene Positionen und History aktualisieren
   updateTrades($signal, $openPositions, $history);
}


/**
 * Aktualisiert die gespeicherten offenen Positionen und Historydaten.
 *
 * @param  string $signal               - Signal
 * @param  array  $currentOpenPositions - Array mit aktuell offenen Positionen
 * @param  array  $currentHistory       - Array mit aktuellen Historydaten
 */
function updateTrades($signal, array &$currentOpenPositions, array &$currentHistory) {
   $unchangedPositions = 0;

   // (1) letzten bekannten Stand der offenen Positionen holen
   $knownOpenPositions = OpenPosition ::dao()->listBySignalAlias($signal, $assocTicket=true);

   // (2) offene Positionen abgleichen (sind aufsteigend nach OpenTime+Ticket sortiert)
   foreach ($currentOpenPositions as $i => &$data) {
      $sTicket = (string)$data['ticket'];

      if (!isSet($knownOpenPositions[$sTicket])) {
         $position = OpenPosition ::create($signal, $data)
                                  ->save();
         echoPre('new position: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().'  StopLoss: '.ifNull($position->getStopLoss(), '-').'  TakeProfit: '.ifNull($position->getTakeProfit(),'-'));
      }
      else {
         $sl = $tp = $slMsg = $tpMsg = null;
         // auf modifiziertes SL- oder TP-Limit prüfen
         if ($data['stoploss'  ] != ($sl=$knownOpenPositions[$sTicket]->getStopLoss())  ) $slMsg = '  StopLoss: '  .($sl ? $sl.' => ':'').$data['stoploss'  ];
         if ($data['takeprofit'] != ($tp=$knownOpenPositions[$sTicket]->getTakeProfit())) $tpMsg = '  TakeProfit: '.($tp ? $tp.' => ':'').$data['takeprofit'];
         if ($slMsg || $tpMsg) {
            $position = $knownOpenPositions[$sTicket]->setStopLoss  ($data['stoploss'  ])
                                                     ->setTakeProfit($data['takeprofit'])
                                                     ->save();
            echoPre('modified position: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().$slMsg.$tpMsg);
         }
         else $unchangedPositions++;
         unset($knownOpenPositions[$sTicket]);              // geprüfte Position aus Liste löschen
      }
   }
   $unchangedPositions && echoPre($unchangedPositions.' known position'.($unchangedPositions==1 ? '':'s'));


   // (3) History abgleichen (ist aufsteigend nach CloseTime+OpenTime+Ticket sortiert)
   $closedPositions    = $knownOpenPositions;               // alle in $knownOpenPositions übrig gebliebenen Positionen müssen geschlossen worden sein
   $hstSize            = sizeOf($currentHistory);
   $matchingHstEntries = $newHstEntries = 0;                // nach 3 übereinstimmenden Historyeinträgen wird das Update abgebrochen

   for ($i=$hstSize-1; $i >= 0; $i--) {                     // History wird rückwärts verarbeitet und bricht bei Übereinstimmung der Daten ab (schnellste Variante)
      $data     = $currentHistory[$i];
      $ticket   = $data['ticket'];
      $position = null;
      $wasOpen  = false;

      if ($closedPositions) {
         $sTicket = (string) $ticket;
         if (isSet($closedPositions[$sTicket])) {
            $wasOpen = true;
            // Position aus t_openposition löschen
            echoPre('closed position');
            unset($closedPositions[$sTicket]);
         }
      }
      if (!$wasOpen && ClosedPosition ::dao()->isTicket($signal, $ticket)) {
         $matchingHstEntries++;
         if ($matchingHstEntries >= 3)
            break;
         continue;
      }
      // Position in t_closedposition einfügen
      if ($wasOpen) {
         ClosedPosition ::create($position, $data)->save();
      }
      else {
         ClosedPosition ::create($signal, $data)->save();
         $newHstEntries++;
      }
   }
   $newHstEntries && echoPre($newHstEntries.' new history entr'.($newHstEntries==1 ? 'y':'ies'));

   if ($closedPositions) throw new plRuntimeException('Found '.sizeOf($closedPositions)." orphaned open positions:\n".printFormatted($closedPositions, true));
   echoPre('done');
}


/**
 * Parst eine HTML-Seite.
 *
 * @param  string $signal     - Signal
 * @param  string $html       - Inhalt der HTML-Seite
 * @param  array  $openTrades - Array zur Aufnahme der offenen Positionen
 * @param  array  $history    - Array zur Aufnahme der Accounthistory
 */
function parseHtml($signal, &$html, array &$openTrades, array &$history) {
   global $signals;

   // ggf. RegExp-Stringlimit erhöhen
   $html = str_replace('&nbsp;', ' ', $html);
   if (strLen($html) > (int)ini_get('pcre.backtrack_limit'))
      ini_set('pcre.backtrack_limit', strLen($html));

   $matchedTables = $openTradeRows = $historyRows = $matchedOpenTrades = $matchedHistoryEntries = 0;
   $tables = array();

   // Tabellen <table id="openTrades"> und <table id="history"> extrahieren
   $matchedTables = preg_match_all('/<table\b.*\bid="(opentrades|history)".*>.*<tbody\b.*>(.*)<\/tbody>.*<\/table>/isU', $html, $tables, PREG_SET_ORDER);
   foreach ($tables as $i => &$table) {
      $table[0] = 'table '.($i+1);
      $table[1] = strToLower($table[1]);

      // offene Positionen extrahieren und parsen (Timezone: GMT)
      if ($table[1] == 'opentrades') {
         /*                                                                   // Array([ 0:          ] => {matched html}
         <tr class="red topDir" title="Take Profit: 1.730990 Stop Loss: -">   //       [ 1:TakeProfit] => 1.319590
            <td class="center">2014/09/08 13:25:42</td>                       //       [ 2:StopLoss  ] => -
            <td class="center">1.294130</td>                                  //       [ 3:OpenTime  ] => 2014/09/04 08:15:12
            <td class="center">1.24</td>                                      //       [ 4:OpenPrice ] => 1.314590
            <td class="center">Sell</td>                                      //       [ 5:Lots      ] => 0.16
            <td class="center">EURUSD</td>                                    //       [ 6:Type      ] => Buy
            <td class="center">-32.57</td>                                    //       [ 7:Symbol    ] => EURUSD
            <td class="center">-1.8</td>                                      //       [ 8:Profit    ] => -281.42
            <td class="center">1999552</td>                                   //       [ 9:Pips      ] => -226.9
         </tr>                                                                //       [10:Comment   ] => 2000641)
         */
         $openTradeRows     = preg_match_all('/<tr\b/is', $table[2], $openTrades);
         $matchedOpenTrades = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $openTrades, PREG_SET_ORDER);
         foreach ($openTrades as $i => &$row) {
            if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2]))) throw new plRuntimeException('Error parsing TakeProfit or StopLoss in open position row '.($i+1).":\n".$row[0]);

            // 0:
            //$row[0] = 'row '.($i+1);

            // 1:TakeProfit
            $sValue = trim($row[I_STOP_TAKEPROFIT]);
            if (empty($sValue) || $sValue=='-') $dValue = null;
            else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid TakeProfit found in open position row '.($i+1).': "'.$row[I_STOP_TAKEPROFIT].'"');
            $row['takeprofit'] = $dValue;

            // 2:StopLoss
            $sValue = trim($row[I_STOP_STOPLOSS]);
            if (empty($sValue) || $sValue=='-') $dValue = null;
            else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid StopLoss found in open position row '.($i+1).': "'.$row[I_STOP_STOPLOSS].'"');
            $row['stoploss'] = $dValue;

            // 3:OpenTime
            $sOpenTime = trim($row[I_STOP_OPENTIME]);
            if (!($time=strToTime($sOpenTime.' GMT'))) throw new plRuntimeException('Invalid OpenTime found in open position row '.($i+1).': "'.$row[I_STOP_OPENTIME].'"');
          //$row[I_STOP_OPENTIME] = date(DATE_RFC822, $time);
            $row['opentime' ] = $time;
            $row['closetime'] = null;

            // 4:OpenPrice
            $sValue = trim($row[I_STOP_OPENPRICE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid OpenPrice found in open position row '.($i+1).': "'.$row[I_STOP_OPENPRICE].'"');
            $row['openprice' ] = $dValue;
            $row['closeprice'] = null;

            // 5:LotSize
            $sValue = trim($row[I_STOP_LOTSIZE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid LotSize found in open position row '.($i+1).': "'.$row[I_STOP_LOTSIZE].'"');
            $row['lots'] = $dValue;

            // 6:Type
            $sValue = trim(strToLower($row[I_STOP_TYPE]));
            if ($sValue!='buy' && $sValue!='sell') throw new plRuntimeException('Invalid OperationType found in open position row '.($i+1).': "'.$row[I_STOP_TYPE].'"');
            $row['type'] = $sValue;

            // 7:Symbol
            $sValue = trim($row[I_STOP_SYMBOL]);
            if (empty($sValue)) throw new plRuntimeException('Invalid Symbol found in open position row '.($i+1).': "'.$row[I_STOP_SYMBOL].'"');
            $row['symbol'] = $sValue;

            // 8:Profit
            $sValue = trim($row[I_STOP_PROFIT]);
            if (!is_numeric($sValue)) throw new plRuntimeException('Invalid Profit found in open position row '.($i+1).': "'.$row[I_STOP_PROFIT].'"');
            $row['profit'    ] = (float)$sValue;
            $row['commission'] = 0;
            $row['swap'      ] = 0;

            // 9:Pips

            // 10:Comment
            $sValue = trim($row[I_STOP_COMMENT]);
            if (!ctype_digit($sValue)) throw new plRuntimeException('Invalid Comment found in open position row '.($i+1).': "'.$row[I_STOP_COMMENT].'" (non-digits)');
            $row['ticket'] = (int)$sValue;

            unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10]);
         }
         // offene Positionen sortieren
         uSort($openTrades, 'compareTradesByOpenTimeTicket');
      }

      // History extrahieren und parsen; TP und SL werden, falls angegeben, erkannt (Timezone: GMT)
      if ($table[1] == 'history') {
         /*                                                                   // Array([ 0:          ] => {matched html}
         <tr class="green">                                                   //       [ 1:TakeProfit] =>
            <td class="center">2014/09/05 16:32:52</td>                       //       [ 2:StopLoss  ] =>
            <td class="center">2014/09/08 04:57:25</td>                       //       [ 3:OpenTime  ] => 2014/09/09 13:05:58
            <td class="center">1.294620</td>                                  //       [ 4:CloseTime ] => 2014/09/09 13:08:15
            <td class="center">1.294130</td>                                  //       [ 5:OpenPrice ] => 1.742870
            <td class="center">1.24</td>                                      //       [ 6:ClosePrice] => 1.743470
            <td class="center">Sell</td>                                      //       [ 7:Lots      ] => 0.12
            <td class="center">EURUSD</td>                                    //       [ 8:Type      ] => Sell
            <td class="center">47.43</td>                                     //       [ 9:Symbol    ] => GBPAUD
            <td class="center">4.9</td>                                       //       [10:Profit    ] => -7.84
            <td class="center">1996607</td>                                   //       [11:Pips      ] => -6
         </tr>                                                                //       [12:Comment   ] => 2002156)
         */
         $historyRows           = preg_match_all('/<tr\b/is', $table[2], $history);
         $matchedHistoryEntries = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $history, PREG_SET_ORDER);
         foreach ($history as $i => &$row) {
            if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2]))) throw new plRuntimeException('Error parsing TakeProfit or StopLoss in history row '.($i+1).":\n".$row[0]);

            // 0:
            //$row[0] = 'row '.($i+1);

            // 1:TakeProfit
            $sValue = trim($row[I_STH_TAKEPROFIT]);
            if (empty($sValue) || $sValue=='-') $dValue = null;
            else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid TakeProfit found in history row '.($i+1).': "'.$row[I_STH_TAKEPROFIT].'"');
            $row['takeprofit'] = $dValue;

            // 2:StopLoss
            $sValue = trim($row[I_STH_STOPLOSS]);
            if (empty($sValue) || $sValue=='-') $dValue = null;
            else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid StopLoss found in history row '.($i+1).': "'.$row[I_STH_STOPLOSS].'"');
            $row['stoploss'] = $dValue;

            // 3:OpenTime
            $sOpenTime = trim($row[I_STH_OPENTIME]);
            if (!($time=strToTime($sOpenTime.' GMT'))) throw new plRuntimeException('Invalid OpenTime found in history row '.($i+1).': "'.$row[I_STH_OPENTIME].'"');
            $row['opentime'] = $time;

            // 4:CloseTime
            $sCloseTime = trim($row[I_STH_CLOSETIME]);
            if (!($time=strToTime($sCloseTime.' GMT'))) throw new plRuntimeException('Invalid CloseTime found in history row '.($i+1).': "'.$row[I_STH_CLOSETIME].'"');
            if ($row['opentime'] > $time) {
               if ($signal=='smarttrader' && ($comment=trim($row[I_STH_COMMENT]))=='1175928') {
                  //echoPre("Fixing data error in $signal history (#$comment)");
                  $row['opentime'] = $time;
               }
               else throw new plRuntimeException('Invalid Open-/CloseTime pair found in history row '.($i+1).': "'.$sOpenTime.'" / "'.$sCloseTime.'"');
            }
            $row['closetime'] = $time;

            // 5:OpenPrice
            $sValue = trim($row[I_STH_OPENPRICE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid OpenPrice found in history row '.($i+1).': "'.$row[I_STH_OPENPRICE].'"');
            $row['openprice'] = $dValue;

            // 6:ClosePrice
            $sValue = trim($row[I_STH_CLOSEPRICE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid ClosePrice found in history row '.($i+1).': "'.$row[I_STH_CLOSEPRICE].'"');
            $row['closeprice'] = $dValue;

            // 7:LotSize
            $sValue = trim($row[I_STH_LOTSIZE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid LotSize found in history row '.($i+1).': "'.$row[I_STH_LOTSIZE].'"');
            $row['lots'] = $dValue;

            // 8:Type
            $sValue = trim(strToLower($row[I_STH_TYPE]));
            if ($sValue!='buy' && $sValue!='sell') throw new plRuntimeException('Invalid OperationType found in history row '.($i+1).': "'.$row[I_STH_TYPE].'"');
            $row['type'] = $sValue;

            // 9:Symbol
            $sValue = trim($row[I_STH_SYMBOL]);
            if (empty($sValue)) throw new plRuntimeException('Invalid Symbol found in history row '.($i+1).': "'.$row[I_STH_SYMBOL].'"');
            $row['symbol'] = $sValue;

            // 10:Profit
            $sValue = trim($row[I_STH_PROFIT]);
            if (!is_numeric($sValue)) throw new plRuntimeException('Invalid Profit found in history row '.($i+1).': "'.$row[I_STH_PROFIT].'"');
            $row['profit'    ] = (float)$sValue;
            $row['commission'] = 0;
            $row['swap'      ] = 0;

            // 11:Pips

            // 12:Comment
            $sValue = trim($row[I_STH_COMMENT]);
            if (!ctype_digit($sValue)) throw new plRuntimeException('Invalid Comment found in history row '.($i+1).': "'.$row[I_STH_COMMENT].'" (non-digits)');
            $row['ticket'] = (int)$sValue;

            unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12]);
         }
         // History sortieren
         uSort($history, 'compareTradesByCloseTimeOpenTimeTicket');
      }
   }
   //echoPre($tables);
   //echoPre($matchedTables.' table'.($foundTables==1 ? '':'s'));

   if ($openTradeRows != $matchedOpenTrades    ) throw new plRuntimeException('Could not match '.($openTradeRows-$matchedOpenTrades  ).' row'.($openTradeRows-$matchedOpenTrades  ==1 ? '':'s'));
   if ($historyRows   != $matchedHistoryEntries) throw new plRuntimeException('Could not match '.($historyRows-$matchedHistoryEntries).' row'.($historyRows-$matchedHistoryEntries==1 ? '':'s'));
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
function compareTradesByOpenTimeTicket(array &$tradeA, array &$tradeB) {
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
function compareTradesByCloseTimeOpenTimeTicket(array &$tradeA, array &$tradeB) {
   if ($tradeA['closetime'] > $tradeB['closetime']) return  1;
   if ($tradeA['closetime'] < $tradeB['closetime']) return -1;
   return compareTradesByOpenTimeTicket($tradeA, $tradeB);
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

//$start = $stop = microtime(true);
//echoPre('Execution took '.number_format($stop-$start, 3).' sec');
?>
