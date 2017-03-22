<?php
namespace rosasurfer\myfx\lib\myfxbook;

use rosasurfer\config\Config;
use rosasurfer\core\StaticClass;

use rosasurfer\exception\IOException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;


/**
 * MyfxBook related functionality.
 *
 * All MyfxBook statistics are in unmodified broker times.
 */
class MyfxBook extends StaticClass {


    /** @var string[] - MyfxBook urls */
    private static $urls = [
        'signal'    => 'http://www.myfxbook.com/members/user/signal/{provider_id}',
        'statement' => 'http://www.myfxbook.com/statements/{provider_id}/statement.csv',
    ];


    /**
     * Load the CSV account statement for the specified Signal.
     *
     * @param  Signal $signal
     *
     * @return string - statement content
     */
    public static function loadCsvStatement(\Signal $signal) {
        $localFile = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.$signal->getAlias().'.csv';
        if (is_file($localFile)) return file_get_contents($localFile);

        echoPre('downloading statement...');

        // prepare signal urls
        $providerId = $signal->getProviderId();
        $url        = str_replace('{provider_id}', $providerId, self::$urls['statement']);
        $referer    = str_replace('{provider_id}', $providerId, self::$urls['signal'   ]);

        // simulate standard web browser
        if (!$config=Config::getDefault()) throw new RuntimeException('Service locator returned invalid default config: '.getType($config));
        $request = HttpRequest::create()
                                     ->setUrl($url)
                                     ->setHeader('User-Agent'     ,  $config->get('myfx.useragent')                                  )
                                     ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                                     ->setHeader('Accept-Language', 'en-us'                                                          )
                                     ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7'                                 )
                                     ->setHeader('Connection'     , 'keep-alive'                                                     )
                                     ->setHeader('Cache-Control'  , 'max-age=0'                                                      )
                                     ->setHeader('Referer'        ,  $referer                                                        );

        // use cookies from the specified file
        $cookieFile = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.'cookies.txt';
        $options[CURLOPT_COOKIEFILE] = $cookieFile;                    // read cookies from
        $options[CURLOPT_COOKIEJAR ] = $cookieFile;                    // write cookies to
      //$options[CURLOPT_VERBOSE   ] = true;                           // enable debugging

        // execute HTTP request
        $client   = CurlHttpClient::create($options);
        $response = $client->send($request);
        $content  = $response->getContent();

        if (is_null($content))             throw new IOException('Empty reply from server, url: '.$request->getUrl());
        if ($response->getStatus() != 200) throw new RuntimeException('Unexpected HTTP status code '.($status=$response->getStatus()).' ('.HttpResponse::$sc[$status].') for url: '.$request->getUrl());

        // save as file
        $hFile = fOpen($localFile, 'wb');
        fWrite($hFile, $content);
        fClose($hFile);

        return $content;
    }


    /**
     * Parse a MyfxBook CSV account statement.
     *
     * @param  Signal $signal
     * @param  string $csv       - content of account statement
     * @param  array  $positions - target array to store open positions in
     * @param  array  $history   - target array to store the account history in
     *
     * @return string - NULL or error message if an error occurred
     */
    public static function parseCsvStatement(\Signal $signal, $csv, array &$positions, array &$history) {
        if (!is_string($csv)) throw new IllegalTypeException('Illegal type of parameter $csv: '.getType($csv));
        $positions = $history = [];

        // validate header line
        $separator = "\r\n";
        $line      = strTok($csv, $separator);
        if ($line != 'Open Date,Close Date,Symbol,Action,Lots,SL,TP,Open Price,Close Price,Commission,Swap,Pips,Profit,Gain,Duration (DD:HH:MM:SS),Profitable(%),Profitable(time duration),Drawdown,Risk:Reward,Max(pips),Max(USD),Min(pips),Min(USD),Entry Accuracy(%),Exit Accuracy(%),ProfitMissed(pips),ProfitMissed(USD)')
      //if ($line != 'Open Date,Close Date,Symbol,Action,Lots,SL,TP,Open Price,Close Price,Commission,Swap,Pips,Profit,Gain,                     Duration (DD:HH:MM:SS),Profitable(%),Profitable(time duration),Drawdown,Risk:Reward,Max(pips),Max(USD),Min(pips),Min(USD),Entry Accuracy(%),Exit Accuracy(%),ProfitMissed(pips),ProfitMissed(USD)')
      //if ($line != 'Open Date,Close Date,Symbol,Action,Lots,SL,TP,Open Price,Close Price,Commission,Swap,Pips,Profit,Gain,Comment,Magic Number,Duration (DD:HH:MM:SS),Profitable(%),Profitable(time duration),Drawdown,Risk:Reward,Max(pips),Max(USD),Min(pips),Min(USD),Entry Accuracy(%),Exit Accuracy(%),ProfitMissed(pips),ProfitMissed(USD)')
            throw new RuntimeException('Unknown header line in CSV statement:'.NL.$line);

        $csvDateFormat = '!m/d/Y H:i';

        // validate values of each line
        for ($i=0; ($line=strTok($separator))!==false; $i++) {         // strTok() skips empty lines
            $values = explode(',', $line);
            if (sizeOf($values) != 27) throw new RuntimeException('Unexpected number of values in line '.($i+2).' of CSV statement: '.sizeOf($values));

            // Type
            $type = strToLower($values[I_CSV_ACTION]);
            if ($type == 'deposit')    continue;                        // temporarily skip deposits
            if ($type == 'withdrawal') continue;                        // temporarily skip withdrawals
            $history[$i]['type'] = $type;

            // Ticket
            $history[$i]['ticket'] = $ticket = null;

            // OpenTime
            $date = \DateTime::createFromFormat($csvDateFormat.' O', trim($values[I_CSV_OPEN_DATE]).' +0300'); if (!$date) throw new RuntimeException('Unexpected date/time format in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_OPEN_DATE].'" ('.array_shift(\DateTime::getLastErrors()['errors']).')');
            $history[$i]['opentime'] = $openTime = $date->getTimestamp();

            // CloseTime
            $date = \DateTime::createFromFormat($csvDateFormat.' O', trim($values[I_CSV_CLOSE_DATE]).' +0300'); if (!$date) throw new RuntimeException('Unexpected date/time format in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_CLOSE_DATE].'" ('.array_shift(\DateTime::getLastErrors()['errors']).')');
            if ($openTime > $date->getTimestamp())                                                                         throw new RuntimeException('Illegal open time/close time in data line '.($i+2).' of CSV statement: OpenTime="'.$values[I_CSV_OPEN_DATE].'"  CloseTime="'.$values[I_CSV_CLOSE_DATE].'"');
            $history[$i]['closetime'] = $closeTime= $date->getTimestamp();

            // Symbol
            $sValue = trim($values[I_CSV_SYMBOL]);
            if (!strLen($sValue)) throw new RuntimeException('Unexpected symbol in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_SYMBOL].'"');
            $history[$i]['symbol'] = $symbol = $sValue;;

            // Lots
            $sValue = trim($values[I_CSV_LOTS]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Unexpected lot size in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_LOTS].'"');
            $history[$i]['lots'] = $lots = $dValue;

            // StopLoss
            $sValue = trim($values[I_CSV_STOP_LOSS]);
            if (strLen($sValue) && !is_numeric($sValue)) throw new RuntimeException('Unexpected stop loss in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_STOP_LOSS].'"');
            if (($dValue=(float)$sValue) < 0)            throw new RuntimeException('Unexpected stop loss in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_STOP_LOSS].'"');
            $history[$i]['stoploss'] = $stopLoss = $dValue ? $dValue : null;

            // TakeProfit
            $sValue = trim($values[I_CSV_TAKE_PROFIT]);
            if (strLen($sValue) && !is_numeric($sValue)) throw new RuntimeException('Unexpected take profit in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_TAKE_PROFIT].'"');
            if (($dValue=(float)$sValue) < 0)            throw new RuntimeException('Unexpected take profit in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_TAKE_PROFIT].'"');
            $history[$i]['takeprofit'] = $takeProfit = $dValue ? $dValue : null;

            // OpenPrice
            $sValue = trim($values[I_CSV_OPEN_PRICE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Unexpected open price in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_OPEN_PRICE].'"');
            $history[$i]['openprice'] = $openPrice = $dValue;

            // ClosePrice
            $sValue = trim($values[I_CSV_CLOSE_PRICE]);
            if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new RuntimeException('Unexpected close price in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_CLOSE_PRICE].'"');
            $history[$i]['closeprice'] = $closePrice = $dValue;

            // Commission
            $sValue = trim($values[I_CSV_COMMISSION]);
            if (!is_numeric($sValue)) throw new RuntimeException('Unexpected commission value in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_COMMISSION].'"');
            $history[$i]['commission'] = $commission = (float)$sValue;

            // Swap
            $sValue = trim($values[I_CSV_SWAP]);
            if (!is_numeric($sValue)) throw new RuntimeException('Unexpected swap value in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_SWAP].'"');
            $history[$i]['swap'] = $swap = (float)$sValue;

            // Profit
            $sValue = trim($values[I_CSV_PROFIT]);
            if (!is_numeric($sValue)) throw new RuntimeException('Unexpected profit value in data line '.($i+2).' of CSV statement: "'.$values[I_CSV_PROFIT].'"');
            $history[$i]['netprofit'  ] = $netProfit = (float)$sValue;
            $history[$i]['grossprofit'] = $netProfit + $commission + $swap;

            // MagicNumber
            $history[$i]['magicnumber'] = $magicNumber = null;

            // Comment
            $history[$i]['comment'] = $comment = null;
        }

        // sort history: ORDER BY CloseTime asc, OpenTime asc
        uSort($history, __CLASS__.'::compareTradesByCloseTimeOpenTime');
    }


    /**
     * Comparator function comparing trades by open time.
     *
     * @param  array $tradeA
     * @param  array $tradeB
     *
     * @return int - value greater than zero if $tradeA was opened after $tradeB;
     *               value lower than zero if $tradeA was opened before $tradeB;
     *               0 (zero) if both trades were opened at the same time
     */
    private static function compareTradesByOpenTime(array $tradeA, array $tradeB) {
        if (!$tradeA) return $tradeB ? -1 : 0;
        if (!$tradeB) return $tradeA ?  1 : 0;

        if ($tradeA['opentime'] > $tradeB['opentime']) return  1;
        if ($tradeA['opentime'] < $tradeB['opentime']) return -1;
        return 0;
    }


    /**
     * Comparator function comparing trades first by close time. If close times are equal trades are compared by open time.
     *
     * @param  array $tradeA
     * @param  array $tradeB
     *
     * @return int - value greater than zero if $tradeA was closed after $tradeB;
     *               value lower than zero if $tradeA was closed before $tradeB;
     *               0 (zero) if both trades werde opened and closed at the same times
     */
    private static function compareTradesByCloseTimeOpenTime(array $tradeA, array $tradeB) {
        if (!$tradeA) return $tradeB ? -1 : 0;
        if (!$tradeB) return $tradeA ?  1 : 0;

        if ($tradeA['closetime'] > $tradeB['closetime']) return  1;
        if ($tradeA['closetime'] < $tradeB['closetime']) return -1;
        return self::compareTradesByOpenTime($tradeA, $tradeB);
    }
}
