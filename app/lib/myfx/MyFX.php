<?php
use rosasurfer\core\StaticClass;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;


/**
 * MyFX related functionality
 *
 *                                      size        offset      description
 * struct little-endian MYFX_BAR {      ----        ------      ------------------------------------------------
 *    uint time;                          4            0        FXT-Timestamp (Sekunden seit dem 01.01.1970 FXT)
 *    uint open;                          4            4        in Points
 *    uint high;                          4            8        in Points
 *    uint low;                           4           12        in Points
 *    uint close;                         4           16        in Points
 *    uint ticks;                         4           20
 * };                                  = 24 byte
 *
 *
 *                                      size        offset      description
 * struct little-endian MYFX_TICK {     ----        ------      ------------------------------------------------
 *    uint timeDelta;                     4            0        Millisekunden seit Beginn der Stunde
 *    uint bid;                           4            4        in Points
 *    uint ask;                           4            8        in Points
 * };                                  = 12 byte
 */
class MyFX extends StaticClass {

   /**
    * Struct-Size des MyFX-Bardatenformats (MyFX-Historydateien "M{PERIOD}.myfx")
    */
   const BAR_SIZE = 24;

   /**
    * Struct-Size des MyFX-Tickdatenformats (MyFX-Tickdateien "{HOUR}h_ticks.myfx")
    */
   const TICK_SIZE = 12;

   /**
    * Symbol-Stammdaten
    */
   public static $symbols = array();                              // @see static initializer at the end of file


   /**
    * Gibt den absoluten Pfad der unter dem angegebenen Schlüssel konfigurierten Pfadeinstellung zurück.
    * Ist ein relativer Pfad konfiguriert, wird der Pfad als relativ zu APPLICATION_ROOT interpretiert.
    *
    * @param  string $key - Schlüssel
    *
    * @return string - absoluter Pfad mit Forward-Slashes (auch unter Windows)
    *
    * @throws RuntimeException - wenn unter dem angegebenen Schlüssel keine Pfadeinstellung existiert
    */
   public static function getConfigPath($key) {
      if (!is_string($key)) throw new IllegalTypeException('Illegal type of parameter $key: '.getType($key));

      $directory = str_replace('\\', '/', Config::getDefault()->get($key));    // Backslashes in Konfiguration ersetzen

      if (WINDOWS) {
         if (!preg_match('/^[a-z]:/i', $directory))               // Pfad ist relativ, wenn er nicht mit einem Lw.-Bezeichner beginnt
            $directory = APPLICATION_ROOT.($directory{0}=='/'?'':'/').$directory;
      }
      else if ($directory{0} != '/') {                            // Pfad ist relativ, wenn er nicht mit einem Slash beginnt
         $directory = APPLICATION_ROOT.'/'.$directory;
      }

      return str_replace('\\', '/', $directory);                  // Backslashes in APPLICATION_ROOT ersetzen
   }


   /**
    * Gibt eine gefilterte Anzahl von Symbolstammdaten zurück.
    *
    * @param  array $filter - Bedingungen, nach denen die Symbole zu filtern sind (default: kein Filter)
    *
    * @return array - gefilterte Symbolstammdaten
    */
   public static function filterSymbols(array $filter=null) {
      if (is_null($filter)) return self::$symbols;

      $results = array();
      foreach (self::$symbols as $key => $symbol) {
         foreach ($filter as $field => $value) {
            if (!array_key_exists($field, $symbol)) throw new InvalidArgumentException('Invalid parameter $filter: '.print_r($filter, true));
            if ($symbol[$field] != $value)
               continue 2;
         }
         $results[$key] = $symbol;     // alle Filterbedingungen TRUE
      }
      return $results;
   }


   /**
    * Gibt den FXT-Timestamp der angegebenen Zeit zurück. Ohne Argument wird der FXT-Timestamp der aktuellen Zeit
    * zurückgegeben. Der zurückgegebene Wert sind die Sekunden seit dem 01.01.1970 FXT.
    *
    * @param  int    $time       - Timestamp (default: aktuelle Zeit)
    * @param  string $timezoneId - Timezone-Identifier des Timestamps (default: GMT=Unix-Timestamp).
    *                              Zusätzlich zu den standardmäßigen IDs wird 'FXT' für FXT-basierte Timestamps unterstützt
    *                              (wenn auch explizit selten sinnvoll, da: MyFX::fxtTime($timestamp, 'FXT') == $timestamp).
    *
    * @return int - FXT-Timestamp
    */
   public static function fxtTime($time=null, $timezoneId=null) {
      if (is_null($time)) $time = time();
      else if (!is_int($time))                          throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      if (func_num_args()>1 && !is_string($timezoneId)) throw new IllegalTypeException('Illegal type of parameter $timezoneId: '.getType($timezoneId));

      $gmtTime = null;

      if (is_null($timezoneId) || strToUpper($timezoneId)=='GMT' || strToUpper($timezoneId)=='UTC') {
         $gmtTime = $time;
      }
      else if (strToUpper($timezoneId) == 'FXT') {
         return $time;                                               // Eingabe und Ergebnis sind identisch: Rückkehr
      }
      else {
         // $time in GMT-Timestamp konvertieren
         $oldTimezone = date_default_timezone_get();
         try {
            date_default_timezone_set($timezoneId);

            $offsetA = iDate('Z', $time);
            $gmtTime = $time + $offsetA;                             // $gmtTime ist die GMT-basierte Zeit für $time
            $offsetB = iDate('Z', $gmtTime);
            if ($offsetA != $offsetB) {
               // TODO: wenn DST-Wechsel in genau diesem Zeitfenster
            }

            date_default_timezone_set($oldTimezone);
         }
         catch (\Exception $ex) { date_default_timezone_set($oldTimezone); throw $ex; }
      }


      // GMT-Timestamp in FXT-Timestamp konvertieren
      $oldTimezone = date_default_timezone_get();
      try {
         date_default_timezone_set('America/New_York');

         $estOffset = iDate('Z', $gmtTime);
         $fxtTime   = $gmtTime + $estOffset + 7*HOURS;

         date_default_timezone_set($oldTimezone);
         return $fxtTime;
      }
      catch (\Exception $ex) { date_default_timezone_set($oldTimezone); throw $ex; }
   }


   /**
    * Parst die String-Repräsentation einer FXT-Zeit in einen GMT-Timestamp.
    *
    * @param  string $time - FXT-Zeit in einem der Funktion strToTime() verständlichen Format
    *
    * @return int - Timestamp
    *
    * TODO:  Funktion unnötig: strToTime() überladen und um Erkennung der FXT-Zeitzone erweitern
    */
   public static function fxtStrToTime($time) {
      if (!is_string($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $oldTimezone = date_default_timezone_get();
      try {
         date_default_timezone_set('America/New_York');

         $timestamp = strToTime($time);
         if ($timestamp === false) throw new InvalidArgumentException('Invalid argument $time: "'.$time.'"');
         $timestamp -= 7*HOURS;

         date_default_timezone_set($oldTimezone);
         return $timestamp;
      }
      catch (\Exception $ex) { date_default_timezone_set($oldTimezone); throw $ex; }
   }


   /**
    * Formatiert einen Zeitpunkt als FXT-Zeit.
    *
    * @param  int    $time   - Zeitpunkt (default: aktuelle Zeit)
    * @param  string $format - Formatstring (default: 'Y-m-d H:i:s')
    *
    * @return string - FXT-String
    *
    * Analogous to the date() function except that the time returned is Forex Time (FXT).
    */
   public static function fxtDate($time=null, $format='Y-m-d H:i:s') {
      if (is_null($time)) $time = time();
      else if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));

      // FXT = America/New_York +0700           (von 17:00 bis 24:00 = 7h)
      // date($time+7*HOURS) in der Zone 'America/New_York' reicht nicht aus, da dann keine FXT-Repräsentation
      // von Zeiten, die in New York in eine Zeitumstellung fallen, möglich ist. Dies ist nur mit einer Zone ohne DST
      // möglich. Der GMT-Timestamp muß in einen FXT-Timestamp konvertiert und dieser als GMT-Timestamp formatiert werden.

      return gmDate($format, self::fxtTime($time, 'GMT'));
   }


   /**
    * Gibt den FXT-Offset einer Zeit zu GMT und ggf. die beiden jeweils angrenzenden nächsten DST-Transitionsdaten zurück.
    *
    * @param  int   $time           - GMT-Zeitpunkt (default: aktuelle Zeit)
    * @param  array $prevTransition - Wenn angegeben, enthält diese Variable nach Rückkehr ein Array
    *                                 ['time'=>{timestamp}, 'offset'=>{offset}] mit dem GMT-Timestamp des vorherigen Zeitwechsels
    *                                 und dem Offset vor diesem Zeitpunkt.
    * @param  array $nextTransition - Wenn angegeben, enthält diese Variable nach Rückkehr ein Array
    *                                 ['time'=>{timestamp}, 'offset'=>{offset}] mit dem GMT-Timestamp des nächsten Zeitwechsels
    *                                 und dem Offset nach diesem Zeitpunkt.
    *
    * @return int - Offset in Sekunden oder NULL, wenn der Zeitpunkt außerhalb der bekannten Transitionsdaten liegt.
    *               FXT liegt östlich von GMT, der Offset ist also immer positiv. Es gilt: GMT + Offset = FXT
    *
    *
    * Note: Analog zu date('Z', $time) verhält sich diese Funktion, als wenn lokal die (in PHP nicht existierende) Zeitzone 'FXT'
    *       eingestellt worden wäre.
    */
   public static function fxtTimezoneOffset($time=null, &$prevTransition=array(), &$nextTransition=array()) {
      if (is_null($time)) $time = time();
      else if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      static $transitions = null;
      if (!$transitions) {
         $timezone    = new \DateTimeZone('America/New_York');
         $transitions = $timezone->getTransitions();
      }

      $i = -2;
      foreach ($transitions as $i => $transition) {
         if ($transition['ts'] > $time) {
            $i--;
            break;                                                   // hier zeigt $i auf die aktuelle Periode
         }
      }

      $transSize = sizeOf($transitions);
      $argsSize  = func_num_args();

      // $prevTransition definieren
      if ($argsSize > 1) {
         $prevTransition = array();

         if ($i < 0) {                                               // $transitions ist leer oder $time
            $prevTransition['time'  ] = null;                        // liegt vor der ersten Periode
            $prevTransition['offset'] = null;
         }
         else if ($i == 0) {                                         // $time liegt in erster Periode
            $prevTransition['time'  ] = $transitions[0]['ts'];
            $prevTransition['offset'] = null;                        // vorheriger Offset unbekannt
         }
         else {
            $prevTransition['time'  ] = $transitions[$i  ]['ts'    ];
            $prevTransition['offset'] = $transitions[$i-1]['offset'] + 7*HOURS;
         }
      }

      // $nextTransition definieren
      if ($argsSize > 2) {
         $nextTransition = array();

         if ($i==-2 || $i >= $transSize-1) {                         // $transitions ist leer oder
            $nextTransition['time'  ] = null;                        // $time liegt in letzter Periode
            $nextTransition['offset'] = null;
         }
         else {
            $nextTransition['time'  ] = $transitions[$i+1]['ts'    ];
            $nextTransition['offset'] = $transitions[$i+1]['offset'] + 7*HOURS;
         }
      }

      // Rückgabewert definieren
      $offset = null;
      if ($i >= 0)                                                   // $transitions ist nicht leer und
         $offset = $transitions[$i]['offset'] + 7*HOURS;             // $time liegt nicht vor der ersten Periode
      return $offset;
   }


   /**
    * Gibt die Mailadressen aller konfigurierten Signalempfänger per E-Mail zurück.
    *
    * @return string[] - Array mit E-Mailadressen
    */
   public static function getMailSignalReceivers() {
      static $addresses = null;

      if (is_null($addresses)) {
         $values = Config::getDefault()->get('mail.signalreceivers');
         foreach (explode(',', $values) as $address) {
            if ($address=trim($address))
               $addresses[] = $address;
         }
         if (!$addresses)
            $addresses = array();
      }
      return $addresses;
   }


   /**
    * Gibt die Rufnummern aller konfigurierten Signalempfänger per SMS zurück.
    *
    * @return string[] - Array mit Rufnummern
    */
   public static function getSmsSignalReceivers() {
      static $numbers = null;

      if (is_null($numbers)) {
         $values = Config::getDefault()->get('sms.signalreceivers', null);
         foreach (explode(',', $values) as $number) {
            if ($number=trim($number))
               $numbers[] = $number;
         }
         if (!$numbers)
            $numbers = array();
      }
      return $numbers;
   }


   /**
    * Verschickt eine SMS.
    *
    * @param  string $receiver - Empfänger (internationales Format)
    * @param  string $message  - Nachricht
    */
   public static function sendSMS($receiver, $message) {
      if (!is_string($receiver))   throw new IllegalTypeException('Illegal type of parameter $receiver: '.getType($receiver));
      $receiver = trim($receiver);
      if (strStartsWith($receiver, '+' )) $receiver = subStr($receiver, 1);
      if (strStartsWith($receiver, '00')) $receiver = subStr($receiver, 2);
      if (!ctype_digit($receiver)) throw new InvalidArgumentException('Invalid argument $receiver: "'.$receiver.'"');

      if (!is_string($message))    throw new IllegalTypeException('Illegal type of parameter $message: '.getType($message));
      $message = trim($message);
      if ($message == '')          throw new InvalidArgumentException('Invalid argument $message: "'.$message.'"');


      $config   = Config::getDefault()->get('sms.clickatell');
      $username = $config['username'];
      $password = $config['password'];
      $api_id   = $config['api_id'  ];
      $message  = urlEncode($message);
      $url      = 'https://api.clickatell.com/http/sendmsg?user='.$username.'&password='.$password.'&api_id='.$api_id.'&to='.$receiver.'&text='.$message;

      // HTTP-Request erzeugen und ausführen
      $request  = HttpRequest ::create()->setUrl($url);
      $options[CURLOPT_SSL_VERIFYPEER] = false;                // das SSL-Zertifikat kann nicht prüfbar oder ungültig sein
      $response = CurlHttpClient ::create($options)->send($request);
      $status   = $response->getStatus();
      $content  = $response->getContent();
      if ($status != 200) throw new RuntimeException('Unexpected HTTP status code from api.clickatell.com: '.$status.' ('.HttpResponse ::$sc[$status].')');
   }


   /**
    * Gibt die Beschreibung eines Operation-Types zurück.
    *
    * @param  int $type - Operation-Type
    *
    * @return string - Beschreibung
    */
   public static function operationTypeDescription($type) {
      if (!is_int($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));

      static $operationTypes = array(OP_BUY       => 'Buy'       ,
                                     OP_SELL      => 'Sell'      ,
                                     OP_BUYLIMIT  => 'Buy Limit' ,
                                     OP_SELLLIMIT => 'Sell Limit',
                                     OP_BUYSTOP   => 'Stop Buy'  ,
                                     OP_SELLSTOP  => 'Stop Sell' ,
                                     OP_BALANCE   => 'Balance'   ,
                                     OP_CREDIT    => 'Credit'    ,
                                    );
      if (isSet($operationTypes[$type]))
         return $operationTypes[$type];

      throw new InvalidArgumentException('Invalid parameter $type: '.$type.' (not an operation type)');
   }


   /**
    * Ob ein Zeitpunkt in der Zeitzone FXT auf einen Forex-Handelstag fällt.
    *
    * @param  int    $time       - Timestamp
    * @param  string $timezoneId - Timezone-Identifier des Timestamps (default: GMT=Unix-Timestamp). Zusätzlich zu den
    *                              standardmäßigen IDs wird 'FXT' für FXT-basierte Timestamps unterstützt.
    * @return bool
    */
   public static function isForexTradingDay($time, $timezoneId=null) {
      if (!is_int($time))                           throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      $argsSize = func_num_args();
      if ($argsSize > 1 && !is_string($timezoneId)) throw new IllegalTypeException('Illegal type of parameter $timezoneId: '.getType($timezoneId));

      if ($argsSize == 1)
         return (!self::isForexWeekend($time) && !self::isForexHoliday($time));  // NULL als Timezone-ID ist nicht zulässig

      return (!self::isForexWeekend($time, $timezoneId) && !self::isForexHoliday($time, $timezoneId));
   }


   /**
    * Ob der Wochentag eines Zeitpunkts in der Zeitzone FXT ein Sonnabend oder Sonntag ist.
    *
    * @param  int    $time       - Timestamp
    * @param  string $timezoneId - Timezone-Identifier des Timestamps (default: GMT=Unix-Timestamp). Zusätzlich zu den
    *                              standardmäßigen IDs wird 'FXT' für FXT-basierte Timestamps unterstützt.
    * @return bool
    */
   public static function isForexWeekend($time, $timezoneId=null) {
      if (!is_int($time))                           throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      $argsSize = func_num_args();
      if ($argsSize > 1 && !is_string($timezoneId)) throw new IllegalTypeException('Illegal type of parameter $timezoneId: '.getType($timezoneId));

      // $time in FXT-Timestamp konvertieren
      if ($argsSize == 1) $fxtTime = self::fxtTime($time);                 // NULL als Timezone-ID ist nicht zulässig
      else                $fxtTime = self::fxtTime($time, $timezoneId);

      // fxtTime als GMT-Timestamp prüfen
      $dow = (int) gmDate('w', $fxtTime);
      return ($dow==SATURDAY || $dow==SUNDAY);
   }


   /**
    * Ob ein Zeitpunkt in der Zeitzone FXT auf einen Forex-Feiertag fällt.
    *
    * @param  int    $time       - Timestamp
    * @param  string $timezoneId - Timezone-Identifier des Timestamps (default: GMT=Unix-Timestamp). Zusätzlich zu den
    *                              standardmäßigen IDs wird 'FXT' für FXT-basierte Timestamps unterstützt.
    * @return bool
    */
   public static function isForexHoliday($time, $timezoneId=null) {
      if (!is_int($time))                           throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      $argsSize = func_num_args();
      if ($argsSize > 1 && !is_string($timezoneId)) throw new IllegalTypeException('Illegal type of parameter $timezoneId: '.getType($timezoneId));

      // $time in FXT-Timestamp konvertieren
      if ($argsSize == 1) $fxtTime = self::fxtTime($time);                 // NULL als Timezone-ID ist nicht zulässig
      else                $fxtTime = self::fxtTime($time, $timezoneId);

      // fxtTime als GMT-Timestamp prüfen
      $dom = (int) gmDate('j', $time);
      $m   = (int) gmDate('n', $time);

      if ($dom==1 && $m==1)            // 1. Januar
         return true;
      if ($dom==25 && $m==12)          // 25. Dezember
         return true;
      return false;
   }


   /**
    * Interpretiert die MyFX-Bardaten eines Strings und liest sie in ein Array ein. Die resultierenden Bars werden
    * beim Lesen validiert.
    *
    * @param  string $data   - String mit MyFX-Bardaten
    * @param  string $symbol - Meta-Information für eine evt. Fehlermeldung (falls die Daten fehlerhaft sind)
    *
    * @return MYFX_BAR[] - Array mit Bardaten
    */
   public static function readBarData($data, $symbol) {
      if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

      $lenData = strLen($data); if ($lenData % MyFX::BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol.' data: '.$lenData.' (not an even MyFX::BAR_SIZE)');
      $offset  = 0;
      $bars    = array();
      $i       = -1;

      while ($offset < $lenData) {
         $i++;
         $bars[] = unpack("@$offset/Vtime/Vopen/Vhigh/Vlow/Vclose/Vticks", $data);
         $offset += MyFX::BAR_SIZE;

         // Bars validieren
         if ($bars[$i]['open' ] > $bars[$i]['high'] ||      // aus (H >= O && O >= L) folgt (H >= L)
             $bars[$i]['open' ] < $bars[$i]['low' ] ||      // nicht mit min()/max(), da nicht performant
             $bars[$i]['close'] > $bars[$i]['high'] ||
             $bars[$i]['close'] < $bars[$i]['low' ] ||
            !$bars[$i]['ticks']) throw new RuntimeException("Illegal $symbol data for bar[$i]: O={$bars[$i]['open']} H={$bars[$i]['high']} L={$bars[$i]['low']} C={$bars[$i]['close']} V={$bars[$i]['ticks']} T='".gmDate('D, d-M-Y H:i:s', $bars[$i]['time'])."'");
      }
      return $bars;
   }


   /**
    * Interpretiert die Bardaten einer MyFX-Datei und liest sie in ein Array ein.
    *
    * @param  string $fileName - Name der Datei mit MyFX-Bardaten
    * @param  string $symbol   - Meta-Information für eine evt. Fehlermeldung (falls die Daten fehlerhaft sind)
    *
    * @return MYFX_BAR[] - Array mit Bardaten
    */
   public static function readBarFile($fileName, $symbol) {
      if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
      return self::readBarData(file_get_contents($fileName), $symbol);
   }


   /**
    * Interpretiert die Bardaten einer komprimierten MyFX-Datei und liest sie in ein Array ein.
    *
    * @param  string $fileName - Name der Datei mit MyFX-Bardaten
    *
    * @return MYFX_BAR[] - Array mit Bardaten
    */
   public static function readCompressedBarFile($fileName) {
      throw new UnimplementedFeatureException(__METHOD__);
   }


   /**
    * Gibt den Offset eines Zeitpunktes innerhalb einer Zeitreihe zurück.
    *
    * @param  array $series - zu durchsuchende Reihe: Zeiten, Arrays mit dem Feld 'time' oder Objekte mit der Methode getTime()
    * @param  int   $time   - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn der Offset außerhalb der Arraygrenzen liegt
    */
   public static function findTimeOffset(array $series, $time) {
      if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size  = sizeof($series); if (!$size) return -1;
      $i     = -1;
      $iFrom =  0;

      // Zeiten
      if (is_int($series[0])) {
         $iTo = $size-1; if ($series[$iTo] < $time) return -1;

         while (true) {                                           // Zeitfenster von Beginn- und Endbar rekursiv bis zum
            if ($series[$iFrom] >= $time) {                       // gesuchten Zeitpunkt verkleinern
               $i = $iFrom;
               break;
            }
            if ($series[$iTo]==$time || $size==2) {
               $i = $iTo;
               break;
            }
            $midSize = (int) ceil($size/2);                       // Fenster halbieren
            $iMid    = $iFrom + $midSize - 1;
            if ($series[$iMid] <= $time) $iFrom = $iMid;
            else                         $iTo   = $iMid;
            $size = $iTo - $iFrom + 1;
         }
         return $i;
      }

      // Arrays
      if (is_array($series[0])) {
         if (!is_int($series[0]['time'])) throw new IllegalTypeException('Illegal type of element $series[0][time]: '.getType($series[0]['time']));
         $iTo = $size-1; if ($series[$iTo]['time'] < $time) return -1;

         while (true) {                                           // Zeitfenster von Beginn- und Endbar rekursiv bis zum
            if ($series[$iFrom]['time'] >= $time) {               // gesuchten Zeitpunkt verkleinern
               $i = $iFrom;
               break;
            }
            if ($series[$iTo]['time']==$time || $size==2) {
               $i = $iTo;
               break;
            }
            $midSize = (int) ceil($size/2);                       // Fenster halbieren
            $iMid    = $iFrom + $midSize - 1;
            if ($series[$iMid]['time'] <= $time) $iFrom = $iMid;
            else                                 $iTo   = $iMid;
            $size = $iTo - $iFrom + 1;
         }
         return $i;
      }

      // Objekte
      if (is_object($series[0])) {
         if (!is_int($series[0]->getTime())) throw new IllegalTypeException('Illegal type of property $series[0]->getTime(): '.getType($series[0]->getTime()));
         $iTo = $size-1; if ($series[$iTo]->getTime() < $time) return -1;

         while (true) {                                           // Zeitfenster von Beginn- und Endbar rekursiv bis zum
            if ($series[$iFrom]->getTime() >= $time) {            // gesuchten Zeitpunkt verkleinern
               $i = $iFrom;
               break;
            }
            if ($series[$iTo]->getTime()==$time || $size==2) {
               $i = $iTo;
               break;
            }
            $midSize = (int) ceil($size/2);                       // Fenster halbieren
            $iMid    = $iFrom + $midSize - 1;
            if ($series[$iMid]->getTime() <= $time) $iFrom = $iMid;
            else                                    $iTo   = $iMid;
            $size = $iTo - $iFrom + 1;
         }
         return $i;
      }

      throw new IllegalTypeException('Illegal type of element $series[0]: '.getType($series[0]));
   }


   /**
    * Gibt den Offset der Bar zurück, die den angegebenen Zeitpunkt exakt abdeckt.
    *
    * @param  array $bars   - zu durchsuchende Bars: MYFX_BARs oder HISTORY_BARs
    * @param  int   $period - Barperiode
    * @param  int   $time   - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn keine solche Bar existiert
    */
   public static function findBarOffset(array $bars, $period, $time) {
      if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));
      if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
      if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size = sizeOf($bars);
      if (!$size)
         return -1;

      $offset = MyFX::findTimeOffset($bars, $time);

      if ($offset < 0) {                                                         // Zeitpunkt liegt nach der jüngsten bar[openTime]
         $closeTime = self::periodCloseTime($bars[$size-1]['time'], $period);
         if ($time < $closeTime)                                                 // Zeitpunkt liegt innerhalb der jüngsten Bar
            return $size-1;
         return -1;
      }

      if ($bars[$offset]['time'] == $time)                                       // Zeitpunkt liegt exakt auf der jeweiligen Bar
         return $offset;

      if ($offset == 0)                                                          // Zeitpunkt ist älter die älteste Bar
         return -1;

      $offset--;
      $closeTime = self::periodCloseTime($bars[$offset]['time'], $period);
      if ($time < $closeTime)                                                    // Zeitpunkt liegt in der vorhergehenden Bar
         return $offset;
      return -1;                                                                 // Zeitpunkt liegt nicht in der vorhergehenden Bar,
   }                                                                             // also Lücke zwischen der vorhergehenden und der
                                                                                 // folgenden Bar

   /**
    * Gibt den Offset der Bar zurück, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar, wird der Offset
    * der letzten vorhergehenden Bar zurückgegeben.
    *
    * @param  array $bars   - zu durchsuchende Bars: MYFX_BARs oder HISTORY_BARs
    * @param  int   $period - Barperiode
    * @param  int   $time   - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist älter als die älteste Bar)
    */
   public static function findBarOffsetPrevious(array $bars, $period, $time) {
      if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));
      if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
      if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size = sizeOf($bars);
      if (!$size)
         return -1;

      $offset = MyFX::findTimeOffset($bars, $time);

      if ($offset < 0)                                                           // Zeitpunkt liegt nach der jüngsten bar[openTime]
         return $size-1;

      if ($bars[$offset]['time'] == $time)                                       // Zeitpunkt liegt exakt auf der jeweiligen Bar
         return $offset;
      return $offset - 1;                                                        // Zeitpunkt ist älter als die Bar desselben Offsets
   }


   /**
    * Gibt den Offset der Bar zurück, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar, wird der Offset
    * der nächstfolgenden Bar zurückgegeben.
    *
    * @param  array $bars   - zu durchsuchende Bars: MYFX_BARs oder HISTORY_BARs
    * @param  int   $period - Barperiode
    * @param  int   $time   - Zeitpunkt
    *
    * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist jünger als das Ende der jüngsten Bar)
    */
   public static function findBarOffsetNext(array $bars, $period, $time) {
      if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));
      if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
      if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $size = sizeOf($bars);
      if (!$size)
         return -1;

      $offset = self::findTimeOffset($bars, $time);

      if ($offset < 0) {                                                         // Zeitpunkt liegt nach der jüngsten bar[openTime]
         $closeTime = self::periodCloseTime($bars[$size-1]['time'], $period);
         return ($closeTime > $time) ? $size-1 : -1;
      }
      if ($offset == 0)                                                          // Zeitpunkt liegt vor oder exakt auf der ersten Bar
         return 0;

      if ($bars[$offset]['time'] == $time)                                       // Zeitpunkt stimmt mit bar[openTime] überein
         return $offset;
      $offset--;                                                                 // Zeitpunkt liegt in der vorherigen oder zwischen der
                                                                                 // vorherigen und der TimeOffset-Bar
      $closeTime = self::periodCloseTime($bars[$offset]['time'], $period);
      if ($closeTime > $time)                                                    // Zeitpunkt liegt innerhalb dieser vorherigen Bar
         return $offset;
      return ($offset+1 < $bars) ? $offset+1 : -1;                               // Zeitpunkt liegt nach bar[closeTime], also Lücke...
   }                                                                             // zwischen der vorherigen und der folgenden Bar


   /**
    * Gibt die lesbare Konstante eines Timeframe-Codes zurück.
    *
    * @param  int period - Timeframe-Code bzw. Anzahl der Minuten je Bar
    *
    * @return string
    */
   public static function periodToStr($period) {
      if (!is_int($period)) throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));

      switch ($period) {
         case PERIOD_M1 : return "PERIOD_M1";       // 1 minute
         case PERIOD_M5 : return "PERIOD_M5";       // 5 minutes
         case PERIOD_M15: return "PERIOD_M15";      // 15 minutes
         case PERIOD_M30: return "PERIOD_M30";      // 30 minutes
         case PERIOD_H1 : return "PERIOD_H1";       // 1 hour
         case PERIOD_H4 : return "PERIOD_H4";       // 4 hour
         case PERIOD_D1 : return "PERIOD_D1";       // 1 day
         case PERIOD_W1 : return "PERIOD_W1";       // 1 week
         case PERIOD_MN1: return "PERIOD_MN1";      // 1 month
         case PERIOD_Q1 : return "PERIOD_Q1";       // 1 quarter
      }
      return "$period";
   }


   /**
    * Alias für periodToStr()
    *
    * @param  int timeframe
    *
    * @return string
    */
   public static function timeframeToStr($timeframe) {
      return self::periodToStr($timeframe);
   }


   /**
    * Gibt die Beschreibung eines Timeframe-Codes zurück.
    *
    * @param  int period - Timeframe-Code bzw. Anzahl der Minuten je Bar
    *
    * @return string
    */
   public static function periodDescription($period) {
      if (!is_int($period)) throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));

      switch ($period) {
         case PERIOD_M1 : return "M1";      //      1  1 minute
         case PERIOD_M5 : return "M5";      //      5  5 minutes
         case PERIOD_M15: return "M15";     //     15  15 minutes
         case PERIOD_M30: return "M30";     //     30  30 minutes
         case PERIOD_H1 : return "H1";      //     60  1 hour
         case PERIOD_H4 : return "H4";      //    240  4 hour
         case PERIOD_D1 : return "D1";      //   1440  daily
         case PERIOD_W1 : return "W1";      //  10080  weekly
         case PERIOD_MN1: return "MN1";     //  43200  monthly
         case PERIOD_Q1 : return "Q1";      // 129600  3 months (a quarter)
      }
      return "$period";
   }


   /**
    * Alias für periodDescription()
    *
    * @param  int timeframe
    *
    * @return string
    */
   public static function timeframeDescription($timeframe) {
      return self::periodDescription($timeframe);
   }


   /**
    * Gibt die CloseTime der Periode zurück, die die angegebene Zeit abdeckt.
    *
    * @param  int  $time   - Zeitpunkt
    * @param  int  $period - Periode
    *
    * @return int - Zeit
    */
   public static function periodCloseTime($time, $period) {
      if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
      if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));
      if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');

      if ($period <= PERIOD_D1) {
         $openTime  = $time - $time%$period*MINUTES;
         $closeTime = $openTime + $period*MINUTES;
      }
      else if ($period == PERIOD_W1) {
         $dow       = (int) gmDate('w', $time);
         $openTime  = $time - $time%DAY - (($dow+6)%7)*DAYS;         // 00:00, Montag
         $closeTime = $openTime + 1*WEEK;                            // 00:00, nächster Montag
      }
      else /*PERIOD_MN1*/ {
         $m         = (int) gmDate('m', $time);
         $y         = (int) gmDate('Y', $time);
         $closeTime = gmMkTime(0, 0, 0, $m+1, 1, $y);                // 00:00, 1. des nächsten Monats
      }

      return $closeTime;
   }


   /**
    * Erzeugt und verwaltet dynamisch generierte Variablen.
    *
    * Evaluiert und cacht häufig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
    * da die Variablen nicht global gespeichert oder über viele Funktionsaufrufe hinweg weitergereicht werden müssen,
    * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
    *
    * @param  string $id     - eindeutiger Bezeichner der Variable
    * @param  string $symbol - Symbol oder NULL
    * @param  int    $time   - Timestamp oder NULL
    *
    * @return string - Variable
    */
   public static function getVar($id, $symbol=null, $time=null) {
      static $varCache = array();
      if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
         return $varCache[$key];

      if (!is_string($id))                          throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
      if (!is_null($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
      if (!is_null($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

      $me = __FUNCTION__;

      if ($id == 'myfxDirDate') {                  // $yyyy/$mm/$dd                                            // lokales Pfad-Datum
         if (!$time)   throw new InvalidArgumentException('Invalid parameter $time: '.$time);
         $result = gmDate('Y/m/d', $time);
      }
      else if ($id == 'myfxDir') {                 // $dataDirectory/history/myfx/$type/$symbol/$myfxDirDate   // lokales Verzeichnis
         if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
         static $dataDirectory; if (!$dataDirectory)
         $dataDirectory = self::getConfigPath('myfx.data_directory');
         $type          = self::$symbols[$symbol]['type'];
         $myfxDirDate   = self::$me('myfxDirDate', null, $time);
         $result        = "$dataDirectory/history/myfx/$type/$symbol/$myfxDirDate";
      }
      else if ($id == 'myfxFile.M1.raw') {         // $myfxDir/M1.myfx                                         // MyFX-M1-Datei ungepackt
         $myfxDir = self::$me('myfxDir' , $symbol, $time);
         $result  = "$myfxDir/M1.myfx";
      }
      else if ($id == 'myfxFile.M1.compressed') {  // $myfxDir/M1.rar                                          // MyFX-M1-Datei gepackt
         $myfxDir = self::$me('myfxDir' , $symbol, $time);
         $result  = "$myfxDir/M1.rar";
      }
      else throw new InvalidArgumentException('Unknown parameter $id: "'.$id.'"');

      $varCache[$key] = $result;
      (sizeof($varCache) > ($maxSize=256)) && array_shift($varCache)/* && echoPre('var cache size limit of '.$maxSize.' hit')*/;

      return $result;
   }
}


/**
 * Workaround für in PHP nicht existierende Static Initializer
 */
MyFX::$symbols = array('AUDUSD' => array('type'=>'forex', 'name'=>'AUDUSD', 'description'=>'Australian Dollar vs US Dollar'  , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-08-03 21:00:00 GMT'), 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'EURUSD' => array('type'=>'forex', 'name'=>'EURUSD', 'description'=>'Euro vs US Dollar'               , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'GBPUSD' => array('type'=>'forex', 'name'=>'GBPUSD', 'description'=>'Great Britain Pound vs US Dollar', 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'NZDUSD' => array('type'=>'forex', 'name'=>'NZDUSD', 'description'=>'New Zealand Dollar vs US Dollar' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-08-03 21:00:00 GMT'), 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'USDCAD' => array('type'=>'forex', 'name'=>'USDCAD', 'description'=>'US Dollar vs Canadian Dollar'    , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-08-03 21:00:00 GMT'), 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'USDCHF' => array('type'=>'forex', 'name'=>'USDCHF', 'description'=>'US Dollar vs Swiss Franc'        , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'USDJPY' => array('type'=>'forex', 'name'=>'USDJPY', 'description'=>'US Dollar vs Japanese Yen'       , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>array('ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')), 'provider'=>'dukascopy'),
                       'USDNOK' => array('type'=>'forex', 'name'=>'USDNOK', 'description'=>'US Dollar vs Norwegian Krona'    , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-08-04 00:00:00 GMT'), 'M1'=>strToTime('2003-08-05 00:00:00 GMT')), 'provider'=>'dukascopy'),     // TODO: M1-Start ist der 04.08.2003
                       'USDSEK' => array('type'=>'forex', 'name'=>'USDSEK', 'description'=>'US Dollar vs Swedish Kronor'     , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2003-08-04 00:00:00 GMT'), 'M1'=>strToTime('2003-08-05 00:00:00 GMT')), 'provider'=>'dukascopy'),     // TODO: M1-Start ist der 04.08.2003
                       'USDSGD' => array('type'=>'forex', 'name'=>'USDSGD', 'description'=>'US Dollar vs Singapore Dollar'   , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('2004-11-16 18:00:00 GMT'), 'M1'=>strToTime('2004-11-17 00:00:00 GMT')), 'provider'=>'dukascopy'),     // TODO: M1-Start ist der 16.11.2004
                       'USDZAR' => array('type'=>'forex', 'name'=>'USDZAR', 'description'=>'US Dollar vs South African Rand' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>strToTime('1997-10-13 18:00:00 GMT'), 'M1'=>strToTime('1997-10-14 00:00:00 GMT')), 'provider'=>'dukascopy'),     // TODO: M1-Start ist der 13.11.1997

                       'AUDLFX' => array('type'=>'index', 'name'=>'AUDLFX', 'description'=>'AUD Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'CADLFX' => array('type'=>'index', 'name'=>'CADLFX', 'description'=>'CAD Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'CHFLFX' => array('type'=>'index', 'name'=>'CHFLFX', 'description'=>'CHF Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'EURLFX' => array('type'=>'index', 'name'=>'EURLFX', 'description'=>'EUR Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'GBPLFX' => array('type'=>'index', 'name'=>'GBPLFX', 'description'=>'GBP Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'JPYLFX' => array('type'=>'index', 'name'=>'JPYLFX', 'description'=>'JPY Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'NZDLFX' => array('type'=>'index', 'name'=>'NZDLFX', 'description'=>'NZD Index (LiteForex FX7 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'USDLFX' => array('type'=>'index', 'name'=>'USDLFX', 'description'=>'USD Index (LiteForex FX6 index)' , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),

                       'AUDFX6' => array('type'=>'index', 'name'=>'AUDFX6', 'description'=>'AUD Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'CADFX6' => array('type'=>'index', 'name'=>'CADFX6', 'description'=>'CAD Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'CHFFX6' => array('type'=>'index', 'name'=>'CHFFX6', 'description'=>'CHF Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'EURFX6' => array('type'=>'index', 'name'=>'EURFX6', 'description'=>'EUR Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'GBPFX6' => array('type'=>'index', 'name'=>'GBPFX6', 'description'=>'GBP Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'JPYFX6' => array('type'=>'index', 'name'=>'JPYFX6', 'description'=>'JPY Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'USDFX6' => array('type'=>'index', 'name'=>'USDFX6', 'description'=>'USD Index (FX6 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),

                       'AUDFX7' => array('type'=>'index', 'name'=>'AUDFX7', 'description'=>'AUD Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'CADFX7' => array('type'=>'index', 'name'=>'CADFX7', 'description'=>'CAD Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'CHFFX7' => array('type'=>'index', 'name'=>'CHFFX7', 'description'=>'CHF Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'EURFX7' => array('type'=>'index', 'name'=>'EURFX7', 'description'=>'EUR Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'GBPFX7' => array('type'=>'index', 'name'=>'GBPFX7', 'description'=>'GBP Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'JPYFX7' => array('type'=>'index', 'name'=>'JPYFX7', 'description'=>'JPY Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'NOKFX7' => array('type'=>'index', 'name'=>'NOKFX7', 'description'=>'NOK Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-05 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'NZDFX7' => array('type'=>'index', 'name'=>'NZDFX7', 'description'=>'NZD Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'SEKFX7' => array('type'=>'index', 'name'=>'SEKFX7', 'description'=>'SEK Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-05 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'SGDFX7' => array('type'=>'index', 'name'=>'SGDFX7', 'description'=>'SGD Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2004-11-16 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'USDFX7' => array('type'=>'index', 'name'=>'USDFX7', 'description'=>'USD Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'ZARFX7' => array('type'=>'index', 'name'=>'ZARFX7', 'description'=>'ZAR Index (FX7 index)'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')), 'provider'=>'myfx'     ),

                       'EURX'   => array('type'=>'index', 'name'=>'EURX'  , 'description'=>'EUR Index (ICE)'                 , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-04 00:00:00 GMT')), 'provider'=>'myfx'     ),
                       'USDX'   => array('type'=>'index', 'name'=>'USDX'  , 'description'=>'USD Index (ICE)'                 , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>array('ticks'=>null                                , 'M1'=>strToTime('2003-08-04 00:00:00 GMT')), 'provider'=>'myfx'     ),

                       'XAUUSD' => array('type'=>'metal', 'name'=>'XAUUSD', 'description'=>'Gold vs US Dollar'               , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>array('ticks'=>strToTime('2003-05-05 00:00:00 GMT'), 'M1'=>strToTime('1999-09-02 00:00:00 GMT')), 'provider'=>'dukascopy'),     // TODO: M1-Start ist der 01.09.1999
);
