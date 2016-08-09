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

         // Action (operation type)
         $values[I_CSV_ACTION] = $type = strToLower($values[I_CSV_ACTION]);
         if ($type == 'deposit')    continue;                        // temporarily skip deposits
         if ($type == 'withdrawal') continue;                        // temporarily skip withdrawals

         // Open Date
         $date = \DateTime::createFromFormat($csvDateFormat.' O', trim($values[I_CSV_OPEN_DATE]).' +0300'); if (!$date) throw new RuntimeException('Unexpected date/time format in data line '.$i.' of CSV statement: "'.$values[I_CSV_OPEN_DATE].'" ('.array_shift(\DateTime::getLastErrors()['errors']).')');
         $values[I_CSV_OPEN_DATE] = $openTime = $date->getTimestamp();

         // Close Date
         $date = \DateTime::createFromFormat($csvDateFormat.' O', trim($values[I_CSV_CLOSE_DATE]).' +0300'); if (!$date) throw new RuntimeException('Unexpected date/time format in data line '.$i.' of CSV statement: "'.$values[I_CSV_CLOSE_DATE].'" ('.array_shift(\DateTime::getLastErrors()['errors']).')');
         $values[I_CSV_CLOSE_DATE] = $closeTime= $date->getTimestamp();

         /*
         $values[I_CSV_SYMBOL     ] = 'GBPUSD';                      // Symbol
         $values[I_CSV_LOTS       ] = '0.20';                        // Lots
         $values[I_CSV_STOP_LOSS  ] = '0.00000';                     // SL
         $values[I_CSV_TAKE_PROFIT] = '0.00000';                     // TP
         $values[I_CSV_OPEN_PRICE ] = '1.30377';                     // Open Price
         $values[I_CSV_CLOSE_PRICE] = '1.30451';                     // Close Price
         $values[I_CSV_COMMISSION ] = '-1.5100';                     // Commission
         $values[I_CSV_SWAP       ] = '0.0000';                      // Swap
         $values[I_CSV_NET_PROFIT ] = '13.29';                       // Profit (Net)
         */

         echoPre('OpenTime = '.date(DATE_RFC1123, $openTime));
      }
      echoPre($i.' non-empty lines');
   }
}


// data array indices of CSV statement lines
const I_CSV_OPEN_DATE          =  0;         // Open Date                     '08/08/2016 17:33'      '08/08/2016 01:13'
const I_CSV_CLOSE_DATE         =  1;         // Close Date                    '08/08/2016 22:23'      ''
const I_CSV_SYMBOL             =  2;         // Symbol                        'GBPUSD'                ''
const I_CSV_ACTION             =  3;         // Action                        'Buy|Sell'              'Deposit|Withdrawal'
const I_CSV_LOTS               =  4;         // Lots                          '0.20'                  '0.01'
const I_CSV_STOP_LOSS          =  5;         // SL                            '0.00000'               '0.00000'
const I_CSV_TAKE_PROFIT        =  6;         // TP                            '0.00000'               '0.00000'
const I_CSV_OPEN_PRICE         =  7;         // Open Price                    '1.30377'               '0.00000'
const I_CSV_CLOSE_PRICE        =  8;         // Close Price                   '1.30451'               '0.00000'
const I_CSV_COMMISSION         =  9;         // Commission                    '-1.5100'               '0.0000'
const I_CSV_SWAP               = 10;         // Swap                          '0.0000'                '0.0000'
const I_CSV_PIPS               = 11;         // Pips                          '7.4'                   '0.0'
const I_CSV_NET_PROFIT         = 12;         // Profit                        '13.29'                 '2000.00'
const I_CSV_GAIN               = 13;         // Gain                          '0.07'                  '0'
const I_CSV_DURATION_TIME      = 14;         // Duration (DD:HH:MM:SS)        '00:04:50:05'           '00:00:00:00'
const I_CSV_PROFITABLE_PCT     = 15;         // Profitable(%)                 '69.7'                  ''
const I_CSV_PROFITABLE_TIME    = 16;         // Profitable(time duration)     '3h 22m'                ''
const I_CSV_DRAWDOWN           = 17;         // Drawdown                      '18.0'                  ''
const I_CSV_RISK_REWARD        = 18;         // Risk:Reward                   '1.17'                  ''
const I_CSV_MAX_PIPS           = 19;         // Max(pips)                     '8.3'                   ''
const I_CSV_MAX_USD            = 20;         // Max(USD)                      '16.6'                  ''
const I_CSV_MIN_PIPS           = 21;         // Min(pips)                     '-9.7'                  ''
const I_CSV_MIN_USD            = 22;         // Min(USD)                      '-19.4'                 ''
const I_CSV_ENTRY_ACCURACY_PCT = 23;         // Entry Accuracy(%)             '46.1'                  ''
const I_CSV_EXIT_ACCURACY_PCT  = 24;         // Exit Accuracy(%)              '95.0'                  ''
const I_CSV_PROFIT_MISSED_PIPS = 25;         // ProfitMissed(pips)            '-0.90'                 ''
const I_CSV_PROFIT_MISSED_USD  = 26;         // ProfitMissed(USD)             '-1.80'                 ''
