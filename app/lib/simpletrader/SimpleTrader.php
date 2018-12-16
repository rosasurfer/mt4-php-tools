<?php
namespace rosasurfer\rsx\simpletrader;

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
use rosasurfer\util\PHP;

use rosasurfer\rsx\RSX;
use rosasurfer\rsx\model\signal\ClosedPosition;
use rosasurfer\rsx\model\signal\OpenPosition;
use rosasurfer\rsx\model\signal\Signal;


/**
 * SimpleTrader related functionality.
 */
class SimpleTrader extends StaticClass {


    /** @var string[][] $urls - URLs and referers for downloading a signal's last 500 trades and its full history */
    private static $urls = [
        ['currentHistory' => 'http://cp.forexsignals.com/signal/{provider_signal_id}/signal.html',
         'fullHistory'    => 'http://cp.forexsignals.com/signals.php?do=view&id={provider_signal_id}&showAllClosedTrades=1',
         'referer'        => 'http://cp.forexsignals.com/forex-signals.html'],

        ['currentHistory' => 'https://www.simpletrader.net/signal/{provider_signal_id}/signal.html',
         'fullHistory'    => 'https://www.simpletrader.net/signals.php?do=view&id={provider_signal_id}&showAllClosedTrades=1',
         'referer'        => 'https://www.simpletrader.net/forex-signals.html'],
    ];


    /**
     * Download a HTML page with the trade history of the specified signal. In case of errors retry with alternating URLs.
     *
     * @param  Signal $signal
     * @param  bool   $fullHistory - whether to load the full history or just the last 500 trades
     *
     * @return string - HTML content
     */
    public static function loadSignalPage(Signal $signal, $fullHistory) {
        if (!is_bool($fullHistory)) throw new IllegalTypeException('Illegal type of parameter $fullHistory: '.getType($fullHistory));

        $providerSignalId = $signal->getProviderId();


        // (1) Standard-Browser simulieren
        if (!$config=Config::getDefault()) throw new RuntimeException('Service locator returned invalid default config: '.getType($config));
        $userAgent = $config->get('rsx.useragent');
        if (!strLen($userAgent))           throw new InvalidArgumentException('Invalid user agent configuration: "'.$userAgent.'"');
        $request = HttpRequest::create()
                              ->setHeader('User-Agent'     ,  $userAgent                                                      )
                              ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                              ->setHeader('Accept-Language', 'en-us'                                                          )
                              ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                              ->setHeader('Connection'     , 'keep-alive'                                                     )
                              ->setHeader('Cache-Control'  , 'max-age=0'                                                      );

        // use the specified file for cookies
        $cookieFile = dirName(realPath($_SERVER['PHP_SELF'])).'/cookies.txt';
        $options = [];
        $options[CURLOPT_COOKIEFILE    ] = $cookieFile;                     // read cookies from
        $options[CURLOPT_COOKIEJAR     ] = $cookieFile;                     // write cookies to
        $options[CURLOPT_SSL_VERIFYPEER] = false;                           // an invalid SSL certificate might cause errors

        // TODO: In case of network glitches the script needs to retry until the network is back.


        // (2) execute HTTP request
        $key     = $fullHistory ? 'fullHistory':'currentHistory';
        $counter = 0;
        while (true) {
            $i       = $counter % sizeof(self::$urls);                      // on retries cycle through all urls
            $url     = str_replace('{provider_signal_id}', $providerSignalId, self::$urls[$i][$key]);
            $referer = self::$urls[$i]['referer'];
            $request->setUrl($url)->setHeader('Referer', $referer);

            static $httpClient = null;                                      // only a single instance to use "keep alive"
            !$httpClient && $httpClient=CurlHttpClient::create($options);

            try {
                $counter++;
                $response = $httpClient->send($request);
                                                                            // server error equal to CURLE_GOT_NOTHING
                if (is_null($response->getContent())) throw new IOException('Empty reply from server, url: '.$request->getUrl());
            }
            catch (IOException $ex) {
                $msg = $ex->getMessage();
                if (strStartsWith($msg, 'CURL error CURLE_COULDNT_RESOLVE_HOST') ||
                    strStartsWith($msg, 'CURL error CURLE_COULDNT_CONNECT'     ) ||
                    strStartsWith($msg, 'CURL error CURLE_OPERATION_TIMEDOUT'  ) ||
                    strStartsWith($msg, 'CURL error CURLE_GOT_NOTHING'         ) ||
                    strStartsWith($msg, 'Empty reply from server'              )) {
                    if ($counter < 10) {                                    // up to 10 tries to load a url
                        Logger::log($msg."\nretrying ... ($counter)", L_WARN);
                        sleep(10);                                          // wait a few seconds before retry
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
     * Parse a SimpleTrader HTML page and store the signal data in the passed vars.
     *
     * @param  Signal  $signal     [in]  - signal
     * @param  string  $html       [in]  - HTML content
     * @param  array  &$openTrades [out] - target array to store open positions in
     * @param  array  &$history    [out] - target array to store closed positions in (trade history)
     *
     * @return string|null - NULL or an error message in case of errors
     */
    public static function parseSignalData(Signal $signal, $html, array &$openTrades, array &$history) {
        if (!is_string($html)) throw new IllegalTypeException('Illegal type of parameter $html: '.getType($html));

        $signalAlias = $signal->getAlias();

        // convert HTML entities
        $html = str_replace('&nbsp;', ' ', $html);

        // increase RegExp string limit
        if (strLen($html) > (int)ini_get('pcre.backtrack_limit'))
            PHP::ini_set('pcre.backtrack_limit', strLen($html));

        $matchedTables = $openTradeRows = $historyRows = $matchedOpenTrades = $matchedHistoryEntries = 0;
        $tables = [];

        // extract tables <table id="openTrades"> and <table id="history">
        $matchedTables = preg_match_all('/<table\b.*\bid="(opentrades|history)".*>.*<tbody\b.*>(.*)<\/tbody>.*<\/table>/isU', $html, $tables, PREG_SET_ORDER);
        if ($matchedTables != 2) {
            // invalid login (in case of missing or corrupt cookies)
            if (preg_match('/Please read the following information<\/h4>\s*(You do not have access to view this page\.)/isU', $html, $matches)) throw new RuntimeException($signal->getName().': '.$matches[1]);
            if (preg_match('/Please read the following information<\/h4>\s*(This signal does not exist\.)/isU'              , $html, $matches)) throw new RuntimeException($signal->getName().': '.$matches[1]);

            // various public PHP error messages contained in the transmitted HTML (hello???)
            if (preg_match('/(Parse error: .+ in .+ on line [0-9]+)/iU', $html, $matches)) return $matches[1];    // Parse error: ... in /home/simpletrader/public_html/signals.php on line ...
            if (trim($html) == 'Database error...')                                        return trim($html);    // Database error...

            throw new RuntimeException($signal->getName().': tables "opentrades" and/or "history" not found, HTML:'.NL.NL.$html);
        }

        foreach ($tables as $i => &$table) {
            $table[0] = 'table '.($i+1);
            $table[1] = strToLower($table[1]);

            // extract and parse open positions: timezone = GMT
            if ($table[1] == 'opentrades') {                                        // [ 0:          ] => {matched html}
                /*                                                                  // [ 1:TakeProfit] => 1.319590
                <tr class="red topDir" title="Take Profit: 1.730990 Stop Loss: -">  // [ 2:StopLoss  ] => -                     // if specified
                    <td class="center">2014/09/08 13:25:42</td>                     // [ 3:OpenTime  ] => 2014/09/04 08:15:12
                    <td class="center">1.294130</td>                                // [ 4:OpenPrice ] => 1.314590
                    <td class="center">1.24</td>                                    // [ 5:Lots      ] => 0.16
                    <td class="center">Sell</td>                                    // [ 6:Type      ] => Buy
                    <td class="center">EURUSD</td>                                  // [ 7:Symbol    ] => EURUSD
                    <td class="center">-32.57</td>                                  // [ 8:Profit    ] => -281.42               // NetProfit
                    <td class="center">-1.8</td>                                    // [ 9:Pips      ] => -226.9
                    <td class="center">1999552</td>                                 // [10:Comment   ] => 2000641               // Ticket
                </tr>
                */
                $openTradeRows     = preg_match_all('/<tr\b/is', $table[2], $openTrades);
                $matchedOpenTrades = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $openTrades, PREG_SET_ORDER);
                if (!$openTradeRows) {
                    if (preg_match('/"sEmptyTable": "No trades currently open/', $html)) {
                        // no open positions
                    }
                    else if (preg_match('/"sEmptyTable": "(There are currently trades open[^"]*)"/', $html, $matches)) {
                        // open positions are locked
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
                    else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid TakeProfit found in open position row '.($i+1).': "'.$row[I_OPEN_TAKEPROFIT].'", HTML:'.NL.NL.$row[0]);
                    $row['takeprofit'] = $dValue;

                    // 2:StopLoss
                    $sValue = trim($row[I_OPEN_STOPLOSS]);
                    if (empty($sValue) || $sValue=='-') $dValue = null;
                    else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid StopLoss found in open position row '.($i+1).': "'.$row[I_OPEN_STOPLOSS].'", HTML:'.NL.NL.$row[0]);
                    $row['stoploss'] = $dValue;

                    // 3:OpenTime
                    $sOpenTime = trim($row[I_OPEN_OPENTIME]);
                    if (strEndsWith($sOpenTime, '*'))                               // seen since 06.02.2015 (unknown meaning)
                        $sOpenTime = subStr($sOpenTime, 0, -1);
                    if (!($iTime=strToTime($sOpenTime.' GMT'))) throw new RuntimeException('Invalid OpenTime found in open position row '.($i+1).': "'.$row[I_OPEN_OPENTIME].'", HTML:'.NL.NL.$row[0]);
                    $row['opentime' ] = $iTime;
                    $row['closetime'] = null;

                    // 4:OpenPrice
                    $sValue = trim($row[I_OPEN_OPENPRICE]);
                    if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Invalid OpenPrice found in open position row '.($i+1).': "'.$row[I_OPEN_OPENPRICE].'", HTML:'.NL.NL.$row[0]);
                    $row['openprice' ] = $dValue;
                    $row['closeprice'] = null;

                    // 5:LotSize
                    $sValue = trim($row[I_OPEN_LOTSIZE]);
                    if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Invalid LotSize found in open position row '.($i+1).': "'.$row[I_OPEN_LOTSIZE].'", HTML:'.NL.NL.$row[0]);
                    $row['lots'] = $dValue;

                    // 6:Type
                    $sValue = trim(strToLower($row[I_OPEN_TYPE]));
                    // skip tickets with known errors
                    if ($sValue!='buy' && $sValue!='sell') {
                        $sTicket = trim($row[I_OPEN_COMMENT]);
                        if ($signalAlias=='novolr' && $sTicket=='3488580')          // don't permanently display those errors
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
                    $row['netprofit'  ] = (float)$sValue;

                    // 9:Pips

                    // 10:Comment
                    $sValue = trim($row[I_OPEN_COMMENT]);
                    if (!ctype_digit($sValue)) throw new RuntimeException('Invalid Comment found in open position row '.($i+1).': "'.$row[I_OPEN_COMMENT].'" (non-digits), HTML:'.NL.NL.$row[0]);
                    $row['ticket'] = (int)$sValue;

                    unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10]);
                }; unset($row);

                // sort open positions: ORDER BY OpenTime asc, Ticket asc
                uSort($openTrades, __CLASS__.'::compareTradesByOpenTimeTicket');
            }

            // extract and parse trade history: timezone = GMT
            if ($table[1] == 'history') {                                           // [ 0:          ] => {matched html}
                /*                                                                  // [ 1:TakeProfit] =>                       // if specified
                <tr class="green even">                                             // [ 2:StopLoss  ] =>                       // if specified
                    <td class="center">2015/01/14 14:07:06</td>                     // [ 3:OpenTime  ] => 2014/09/09 13:05:58
                    <td class="center">2015/01/14 14:12:00</td>                     // [ 4:CloseTime ] => 2014/09/09 13:08:15
                    <td class="center">1.183290</td>                                // [ 5:OpenPrice ] => 1.742870
                    <td class="center">1.182420</td>                                // [ 6:ClosePrice] => 1.743470
                    <td class="center">1.60</td>                                    // [ 7:Lots      ] => 0.12
                    <td class="center">Sell</td>                                    // [ 8:Type      ] => Sell
                    <td class="center">EURUSD</td>                                  // [ 9:Symbol    ] => GBPAUD
                    <td class="center">126.40</td>                                  // [10:Profit    ] => -7.84                 // NetProfit
                    <td class="center">8.7</td>                                     // [11:Pips      ] => -6
                    <td class="center">0.07%</td>                                   // [12:Gain      ] => -0.07%
                    <td class="center">2289768</td>                                 // [13:Comment   ] => 2002156               // Ticket
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
                    else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid TakeProfit found in history row '.($i+1).': "'.$row[I_HISTORY_TAKEPROFIT].'", HTML:'.NL.NL.$row[0]);
                    $row['takeprofit'] = $dValue;

                    // 2:StopLoss
                    $sValue = trim($row[I_HISTORY_STOPLOSS]);
                    if (empty($sValue) || $sValue=='-') $dValue = null;
                    else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new RuntimeException('Invalid StopLoss found in history row '.($i+1).': "'.$row[I_HISTORY_STOPLOSS].'", HTML:'.NL.NL.$row[0]);
                    $row['stoploss'] = $dValue;

                    // 3:OpenTime
                    $sOpenTime = trim($row[I_HISTORY_OPENTIME]);
                    if (strEndsWith($sOpenTime, '*'))                               // seen since 06.02.2015 (unknown meaning)
                        $sOpenTime = subStr($sOpenTime, 0, -1);
                    if (!($iTime=strToTime($sOpenTime.' GMT'))) throw new RuntimeException('Invalid OpenTime found in history row '.($i+1).': "'.$row[I_HISTORY_OPENTIME].'", HTML:'.NL.NL.$row[0]);
                    $row['opentime'] = $iTime;

                    // 4:CloseTime
                    $sCloseTime = trim($row[I_HISTORY_CLOSETIME]);
                    if (!($iTime=strToTime($sCloseTime.' GMT'))) throw new RuntimeException('Invalid CloseTime found in history row '.($i+1).': "'.$row[I_HISTORY_CLOSETIME].'", HTML:'.NL.NL.$row[0]);
                    // skip tickets with known errors
                    if ($row['opentime'] > $iTime) {
                        $sTicket = trim($row[I_HISTORY_COMMENT]);
                        $row     = [];
                        if ($signalAlias=='smarttrader' && $sTicket=='1175928') {}  // don't permanently display those errors
                        else echoPre('Skipping invalid ticket #'.$sTicket.': OpenTime='.$sOpenTime.'  CloseTime='.$sCloseTime);
                        continue;
                    }
                    $row['closetime'] = $iTime;

                    // 5:OpenPrice
                    $sValue = trim($row[I_HISTORY_OPENPRICE]);
                    if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Invalid OpenPrice found in history row '.($i+1).': "'.$row[I_HISTORY_OPENPRICE].'", HTML:'.NL.NL.$row[0]);
                    $row['openprice'] = $dValue;

                    // 6:ClosePrice
                    $sValue = trim($row[I_HISTORY_CLOSEPRICE]);
                    if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Invalid ClosePrice found in history row '.($i+1).': "'.$row[I_HISTORY_CLOSEPRICE].'", HTML:'.NL.NL.$row[0]);
                    $row['closeprice'] = $dValue;

                    // 7:LotSize
                    $sValue = trim($row[I_HISTORY_LOTSIZE]);
                    if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Invalid LotSize found in history row '.($i+1).': "'.$row[I_HISTORY_LOTSIZE].'", HTML:'.NL.NL.$row[0]);
                    $row['lots'] = $dValue;

                    // 8:Type
                    $sValue = trim(strToLower($row[I_HISTORY_TYPE]));
                    // fix tickets with known OperationType errors
                    if ($sValue!='buy' && $sValue!='sell') {
                        if (is_numeric($sProfit=trim($row[I_HISTORY_PROFIT])) && $row['openprice'] != $row['closeprice']) {
                            $sTicket = trim($row[I_HISTORY_COMMENT]);
                            $dProfit = (float)$sProfit;
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
                    $row['netprofit'  ] = (float)$sValue;

                    // 11:Pips

                    // 12:Gain

                    // 13:Comment
                    $sValue = trim($row[I_HISTORY_COMMENT]);
                    if (!ctype_digit($sValue)) throw new RuntimeException('Invalid Comment found in history row '.($i+1).': "'.$row[I_HISTORY_COMMENT].'" (non-digits), HTML:'.NL.NL.$row[0]);
                    $row['ticket'] = (int)$sValue;

                    unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12], $row[13]);
                }; unset($row);

                // sort trade history: ORDER BY CloseTime asc, OpenTime asc, Ticket asc
                uSort($history, __CLASS__.'::compareTradesByCloseTimeOpenTimeTicket');
            }
        }; unset($table);

        if ($openTradeRows != $matchedOpenTrades    ) throw new RuntimeException('Could not match '.($openTradeRows-$matchedOpenTrades  ).' row'.pluralize($openTradeRows-$matchedOpenTrades));
        if ($historyRows   != $matchedHistoryEntries) throw new RuntimeException('Could not match '.($historyRows-$matchedHistoryEntries).' row'.pluralize($historyRows-$matchedHistoryEntries));

        return null;
    }


    /**
     * Comparator method comparing two trades. First compare by open time. If considered equal compare by ticket.
     *
     * @param  array $tradeA
     * @param  array $tradeB
     *
     * @return int - positive value if $tradeA was opened after $tradeB;
     *               negative value if $tradeA was opened before $tradeB;
     *               0 (zero) if both trades were opened at the same time
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
     * Comparator method comparing two trades. First compare by close time. If considered equal compare by open time.
     * If still considered equal compare by ticket.
     *
     * @param  array $tradeA
     * @param  array $tradeB
     *
     * @return int - positive value if $tradeA was closed after $tradeB;
     *               negative value if $tradeA was closed before $tradeB;
     *               0 (zero) if both trades were opened and closed at the same times
     */
    private static function compareTradesByCloseTimeOpenTimeTicket(array $tradeA, array $tradeB) {
        if (!$tradeA) return $tradeB ? -1 : 0;
        if (!$tradeB) return $tradeA ?  1 : 0;

        if ($tradeA['closetime'] > $tradeB['closetime']) return  1;
        if ($tradeA['closetime'] < $tradeB['closetime']) return -1;
        return self::compareTradesByOpenTimeTicket($tradeA, $tradeB);
    }


    /**
     * Handle "PositionOpen" events.
     *
     * @param  OpenPosition $position - the opened position
     */
    public static function onPositionOpen(OpenPosition $position) {
        $signal = $position->getSignal();

        // console message
        $consoleMsg = $signal->getName().' opened '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().'  TP: '.ifNull($position->getTakeProfit(), '-').'  SL: '.ifNull($position->getStopLoss(), '-').'  ('.$position->getOpenTime('H:i:s').')';
        echoPre($consoleMsg);

        // notify by e-mail
        try {
            $mailMsg = $signal->getName().' Open '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice();
            foreach (RSX::getMailSignalReceivers() as $receiver) {
                mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
            }
        }
        catch (\Exception $ex) { Logger::log($ex, L_ERROR); }

        // motify by text message if the event occurred at script runtime
        $openTime = RSX::fxtStrToTime($position->getOpenTime());
        if ($openTime >= $_SERVER['REQUEST_TIME']) {
            try {
                $smsMsg = 'Opened '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol()
                         .' @ '.$position->getOpenPrice().(($tp=$position->getTakeProfit()) ? NL.'TP: '.$tp:'')
                                                         .(($sl=$position->getStopLoss()  ) ? ($tp ? '  ':NL).'SL: '.$sl:'').NL.NL
                         .'#'.$position->getTicket().'  ('.$position->getOpenTime('H:i:s').')';

                // warn if the event is older than 2 minutes (trade was published with delay)
                if (($now=time()) > $openTime+2*MINUTES) $smsMsg = 'WARN: '.$smsMsg.' detected at '.date($now); // RSX::fxtDate($now)

                foreach (RSX::getSmsSignalReceivers() as $receiver) {
                    RSX::sendSms($receiver, $smsMsg);
                }
            }
            catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
        }
    }


    /**
     * Handle "PositionModify" events.
     *
     * @param  OpenPosition $position - modified position
     * @param  float        $prevTP   - old TakeProfit value
     * @param  float        $prevSL   - old StopLoss value
     */
    public static function onPositionModify(OpenPosition $position, $prevTP, $prevSL) {
        if (isSet($prevTP) && !is_float($prevTP)) throw new IllegalTypeException('Illegal type of parameter $prevTP: '.getType($prevSL));
        if (isSet($prevSL) && !is_float($prevSL)) throw new IllegalTypeException('Illegal type of parameter $prevSL: '.getType($prevSL));

        $modification = $tpMsg = $slMsg = null;
        if (($current=$position->getTakeProfit()) != $prevTP) $modification .= ($tpMsg=' TP: '.($prevTP ? $prevTP.' => ':'').($current ? $current:'-'));
        if (($current=$position->getStopLoss())   != $prevSL) $modification .= ($slMsg=' SL: '.($prevSL ? $prevSL.' => ':'').($current ? $current:'-'));
        if (!$modification) throw new RuntimeException('No modification found in OpenPosition '.$position);

        $signal = $position->getSignal();

        // console message
        $format = "%-4s %4.2F %s @ %-8s";
        $type   = $position->getType();
        $lots   = $position->getLots();
        $symbol = $position->getSymbol();
        $price  = $position->getOpenPrice();
        $msg    = sprintf($format, ucFirst($type), $lots, $symbol, $price);
        echoPre(date('Y-m-d H:i:s', time()).':  modify '.$msg.$modification);

        // notify by e-mail
        $mailMsg = $signal->getName().': modify '.$msg.$modification;
        try {
            foreach (RSX::getMailSignalReceivers() as $receiver) {
                mail($receiver, $subject=$mailMsg, $mailMsg);
            }
        }
        catch (\Exception $ex) { Logger::log($ex, L_ERROR); }

        // notify by text message
        if (false) {                                                        // atm disabled
            try {
                $smsMsg = $signal->getName().': modified '.str_replace('  ', ' ', $msg)."\n"
                            .$modification                                                ."\n"
                            .date('(H:i:s)', time());                       // RSX::fxtDate(time(), '(H:i:s)')
                foreach (RSX::getSmsSignalReceivers() as $receiver) {
                    RSX::sendSms($receiver, $smsMsg);
                }
            }
            catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
        }
    }


    /**
     * Handle "PositionClose" events.
     *
     * @param  ClosedPosition $position - closed position
     */
    public static function onPositionClose(ClosedPosition $position) {
        $signal = $position->getSignal();

        // console message
        $consoleMsg = $signal->getName().' closed '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().'  Open: '.$position->getOpenPrice().'  Close: '.$position->getClosePrice().'  Profit: '.$position->getNetProfit(2).'  ('.$position->getCloseTime('H:i:s').')';
        echoPre($consoleMsg);

        // notify by e-mail
        try {
            $mailMsg = $signal->getName().' Close '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice();
            foreach (RSX::getMailSignalReceivers() as $receiver) {
                mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
            }
        }
        catch (\Exception $ex) { Logger::log($ex, L_ERROR); }

        // notify by text message if the event occurred at script runtime
        $closeTime = RSX::fxtStrToTime($position->getCloseTime());
        if ($closeTime >= $_SERVER['REQUEST_TIME']) {
            try {
                $smsMsg = 'Closed '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice()."\nOpen: ".$position->getOpenPrice()."\n\n#".$position->getTicket().'  ('.$position->getCloseTime('H:i:s').')';

                // warn if the event is older than 2 minutes (trade was published with delay)
                if (($now=time()) > $closeTime+2*MINUTES) $smsMsg = 'WARN: '.$smsMsg.' detected at '.date($now); // RSX::fxtDate($now)

                foreach (RSX::getSmsSignalReceivers() as $receiver) {
                    RSX::sendSms($receiver, $smsMsg);
                }
            }
            catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
        }
    }


    /**
     * Handle "PositionChange" events.
     *
     * @param  Signal $signal
     * @param  string $symbol
     * @param  array  $report
     * @param  int    $iFirstNewRow
     * @param  string $oldNetPosition
     * @param  string $newNetPosition
     */
    public static function onPositionChange(Signal $signal, $symbol, array $report, $iFirstNewRow, $oldNetPosition, $newNetPosition) {
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

        $lastTradeTime = RSX::fxtStrToTime($report[$rows-1]['time']);

        $msg = $signal->getName().': ';
        if ($i < $rows-1) $msg .= ($rows-$i).' trades in '.$symbol;
        else              $msg .= ($report[$i]['trade']=='open' ? '' : $report[$i]['trade'].' ').ucFirst($report[$i]['type']).' '.numf($report[$i]['lots'], 2).' '.$symbol.' @ '.$report[$i]['price'];

        $subject = $msg;
        $msg .= "\nwas: ".str_replace('  ', ' ', $oldNetPosition);
        $msg .= "\nnow: ".str_replace('  ', ' ', $newNetPosition);
        $msg .= "\n".date('(H:i:s)', $lastTradeTime);               // RSX::fxtDate($lastTradeTime, '(H:i:s)')

        // notify by e-mail
        try {
            foreach (RSX::getMailSignalReceivers() as $receiver) {
                mail($receiver, $subject, $msg);
            }
        }
        catch (\Exception $ex) { Logger::log($ex, L_ERROR); }

        // notify by text message if the event occurred at script runtime
        if ($lastTradeTime >= $_SERVER['REQUEST_TIME']) {
            try {
                // warn if the last trade is older than 2 minutes (trade was published with delay)
                if (($now=time()) > $lastTradeTime+2*MINUTES) $msg = 'WARN: '.$msg.', detected at '.date('H:i:s', $now); // RSX::fxtDate($now, 'H:i:s')

                foreach (RSX::getSmsSignalReceivers() as $receiver) {
                    RSX::sendSms($receiver, $msg);
                }
            }
            catch (\Exception $ex) { Logger::log($ex, L_ERROR); }
        }
    }
}
