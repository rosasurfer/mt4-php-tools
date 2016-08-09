<?php
namespace rosasurfer\myfx\lib\myfxbook;

use rosasurfer\core\StaticClass;

use rosasurfer\exception\IOException;
use rosasurfer\exception\RuntimeException;


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
      $providerId = $signal->getProviderID();
      $url        = str_replace('{provider_id}', $providerId, self::$urls['statement']);
      $referer    = str_replace('{provider_id}', $providerId, self::$urls['signal'   ]);

      // simulate standard web browser
      $request = \HttpRequest::create()
                             ->setUrl($url)
                             ->setHeader('User-Agent'     , \Config::getDefault()->get('myfx.useragent')                     )
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
      $client   = \CurlHttpClient::create($options);
      $response = $client->send($request);
      $content  = $response->getContent();

      if (is_null($content))             throw new IOException('Empty reply from server, url: '.$request->getUrl());
      if ($response->getStatus() != 200) throw new RuntimeException('Unexpected HTTP status code '.($status=$response->getStatus()).' ('.\HttpResponse::$sc[$status].') for url: '.$request->getUrl());

      // save as file
      $hFile = fOpen($localFile, 'wb');
      fWrite($hFile, $content);
      fClose($hFile);

      return $content;
   }


   /**
    * Parse a MyfxBook CSV account statement.
    *
    * @param  Signal  $signal
    * @param  string  $csv       - CSV content of account statement
    * @param  array  &$positions - target array to store open positions
    * @param  array  &$history   - target array to store account history
    *
    * @return string - Fehlermeldung oder NULL, falls kein Fehler auftrat
    */
   public static function parseCsvStatement(\Signal $signal, $csv, array &$positions, array &$history) {
      if (!is_string($csv)) throw new IllegalTypeException('Illegal type of parameter $csv: '.getType($csv));

      // validate file header (line 1)
      $separator = "\r\n";
      $line1     = strTok($csv, $separator);
      if ($line1 != 'Open Date,Close Date,Symbol,Action,Lots,SL,TP,Open Price,Close Price,Commission,Swap,Pips,Profit,Gain,Duration (DD:HH:MM:SS),Profitable(%),Profitable(time duration),Drawdown,Risk:Reward,Max(pips),Max(USD),Min(pips),Min(USD),Entry Accuracy(%),Exit Accuracy(%),ProfitMissed(pips),ProfitMissed(USD)')
         throw new RuntimeException('Invalid or unknown line 1 of CSV statement:'.NL.$line1);

      $csvDateFormat = '!m/d/Y H:i';

      // process each line, validate and normalize values
      for ($i=1; ($line=strTok($separator))!==false && $i++; ) {     // strTok() skips empty lines
         $values = explode(',', $line);
         if (sizeOf($values) != 27) throw new RuntimeException('Unexpected number of values in line '.$i.' of CSV statement: '.sizeOf($values));

         // Operation Type
         $values[I_CSV_ACTION] = $type = strToLower($values[I_CSV_ACTION]);
         if ($type == 'deposit')    continue;                        // temporarily skip deposits
         if ($type == 'withdrawal') continue;                        // temporarily skip withdrawals

         // Open Date
         $date = \DateTime::createFromFormat($csvDateFormat.' O', trim($values[I_CSV_OPEN_DATE]).' +0300'); if (!$date) throw new RuntimeException('Unexpected date/time format in data line '.$i.' of CSV statement: "'.$values[I_CSV_OPEN_DATE].'" ('.array_shift(\DateTime::getLastErrors()['errors']).')');
         $values[I_CSV_OPEN_DATE] = $openTime = $date->getTimestamp();

         // Close Date
         $date = \DateTime::createFromFormat($csvDateFormat.' O', trim($values[I_CSV_CLOSE_DATE]).' +0300'); if (!$date) throw new RuntimeException('Unexpected date/time format in data line '.$i.' of CSV statement: "'.$values[I_CSV_CLOSE_DATE].'" ('.array_shift(\DateTime::getLastErrors()['errors']).')');
         $values[I_CSV_CLOSE_DATE] = $closeTime= $date->getTimestamp();

         // Symbol
         $values[I_CSV_SYMBOL] = $symbol = trim($values[I_CSV_SYMBOL]);

         // Lots
         $sValue = trim($values[I_CSV_LOTS]);
         if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Unexpected lot size in data line '.$i.' of CSV statement: "'.$values[I_CSV_LOTS].'"');
         $values[I_CSV_LOTS] = $lots = $dValue;

         // StopLoss
         $sValue = trim($values[I_CSV_STOP_LOSS]);
         if (strLen($sValue) && !is_numeric($sValue)) throw new RuntimeException('Unexpected stop loss in data line '.$i.' of CSV statement: "'.$values[I_CSV_STOP_LOSS].'"');
         if (($dValue=(double)$sValue) < 0)           throw new RuntimeException('Unexpected stop loss in data line '.$i.' of CSV statement: "'.$values[I_CSV_STOP_LOSS].'"');
         $values[I_CSV_STOP_LOSS] = $stopLoss = $dValue ? $dValue : null;

         // TakeProfit
         $sValue = trim($values[I_CSV_TAKE_PROFIT]);
         if (strLen($sValue) && !is_numeric($sValue)) throw new RuntimeException('Unexpected take profit in data line '.$i.' of CSV statement: "'.$values[I_CSV_TAKE_PROFIT].'"');
         if (($dValue=(double)$sValue) < 0)           throw new RuntimeException('Unexpected take profit in data line '.$i.' of CSV statement: "'.$values[I_CSV_TAKE_PROFIT].'"');
         $values[I_CSV_TAKE_PROFIT] = $takeProfit = $dValue ? $dValue : null;

         // Open Price
         $sValue = trim($values[I_CSV_OPEN_PRICE]);
         if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Unexpected open price in data line '.$i.' of CSV statement: "'.$values[I_CSV_OPEN_PRICE].'"');
         $values[I_CSV_OPEN_PRICE] = $openPrice = $dValue;

         // Close Price
         $sValue = trim($values[I_CSV_CLOSE_PRICE]);
         if (!is_numeric($sValue) || ($dValue=(double)$sValue) <= 0) throw new RuntimeException('Unexpected close price in data line '.$i.' of CSV statement: "'.$values[I_CSV_CLOSE_PRICE].'"');
         $values[I_CSV_CLOSE_PRICE] = $closePrice = $dValue;

         // Commission
         $sValue = trim($values[I_CSV_COMMISSION]);
         if (!is_numeric($sValue)) throw new RuntimeException('Unexpected commission value in data line '.$i.' of CSV statement: "'.$values[I_CSV_COMMISSION].'"');
         $values[I_CSV_COMMISSION] = $commission = (double)$sValue;

         // Swap
         $sValue = trim($values[I_CSV_SWAP]);
         if (!is_numeric($sValue)) throw new RuntimeException('Unexpected swap value in data line '.$i.' of CSV statement: "'.$values[I_CSV_SWAP].'"');
         $values[I_CSV_SWAP] = $swap = (double)$sValue;

         // NetProfit
         $sValue = trim($values[I_CSV_NET_PROFIT]);
         if (!is_numeric($sValue)) throw new RuntimeException('Unexpected profit value in data line '.$i.' of CSV statement: "'.$values[I_CSV_NET_PROFIT].'"');
         $values[I_CSV_NET_PROFIT] = $profit = (double)$sValue;
      }
   }
}


// import namespace constants
!defined('I_CSV_OPEN_DATE') && include(__DIR__.'/constants.php');
