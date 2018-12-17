<?php
namespace rosasurfer\rsx;

use rosasurfer\config\Config;
use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rsx\metatrader\MT4;


/**
 * RSX related functionality
 *
 *                                             size        offset      description
 * struct RSX_PRICE_BAR {                      ----        ------      ------------------------------------------------
 *    uint time;                                 4            0        FXT timestamp (seconds since 01.01.1970 FXT)
 *    uint open;                                 4            4        in point
 *    uint high;                                 4            8        in point
 *    uint low;                                  4           12        in point
 *    uint close;                                4           16        in point
 *    uint ticks;                                4           20
 * };                                    = 24 byte
 *
 *                                             size        offset      description
 * struct RSX_PIP_BAR {                        ----        ------      ------------------------------------------------
 *    uint   time;                               4            0        FXT timestamp (seconds since 01.01.1970 FXT)
 *    double open;                               8            4        in pip
 *    double high;                               8           12        in pip
 *    double low;                                8           20        in pip
 *    double close;                              8           28        in pip
 * };                                    = 36 byte
 *
 *                                             size        offset      description
 * struct RSX_TICK {                           ----        ------      ------------------------------------------------
 *    uint timeDelta;                            4            0        milliseconds since beginning of the hour
 *    uint bid;                                  4            4        in point
 *    uint ask;                                  4            8        in point
 * };                                    = 12 byte
 */
class RSX extends StaticClass {


    /**
     * struct size in bytes of a RSX_PRICE_BAR (format of RSX history files "M{PERIOD}.myfx")
     */
    const BAR_SIZE = 24;

    /**
     * struct size in bytes of a RSX tick (format of RSX tick files "{HOUR}h_ticks.myfx")
     */
    const TICK_SIZE = 12;


    /** @var array $symbols - symbols meta data (static initializer at the end of this file) */
    public static $symbols = [];


    /**
     * Gibt eine gefilterte Anzahl von Symbolstammdaten zurueck.
     *
     * @param  array $filter - Bedingungen, nach denen die Symbole zu filtern sind (default: kein Filter)
     *
     * @return array - gefilterte Symbolstammdaten
     */
    public static function filterSymbols(array $filter=null) {
        if (is_null($filter)) return self::$symbols;

        $results = [];
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
     * Parst die String-Repraesentation einer FXT-Zeit in einen GMT-Timestamp.
     *
     * @param  string $time - FXT-Zeit in einem der Funktion strToTime() verstaendlichen Format
     *
     * @return int - Timestamp
     *
     * TODO:  Funktion unnoetig: strToTime() ueberladen und um Erkennung der FXT-Zeitzone erweitern
     */
    public static function fxtStrToTime($time) {
        if (!is_string($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        $oldTimezone = date_default_timezone_get();
        try {
            date_default_timezone_set('America/New_York');

            $timestamp = strToTime($time);
            if ($timestamp === false) throw new InvalidArgumentException('Invalid argument $time: "'.$time.'"');
            $timestamp -= 7*HOURS;

            return $timestamp;
        }
        finally {
            date_default_timezone_set($oldTimezone);
        }
    }


    /**
     * Formatiert einen Zeitpunkt als FXT-Zeit.
     *
     * @param  int    $time   - Zeitpunkt (default: aktuelle Zeit)
     * @param  string $format - Formatstring (default: 'Y-m-d H:i:s')
     *
     * @return string - FXT-String
     *
     * Note: Analogous to the date() function except that the time returned is Forex Time (FXT).
     */
    public static function fxtDate($time=null, $format='Y-m-d H:i:s') {
        if (is_null($time)) $time = time();
        else if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));
        if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));

        // FXT = America/New_York +0700           (von 17:00 bis 24:00 = 7h)
        // date($time+7*HOURS) in der Zone 'America/New_York' reicht nicht aus, da dann keine FXT-Repraesentation
        // von Zeiten, die in New York in eine Zeitumstellung fallen, moeglich ist. Dies ist nur mit einer Zone ohne DST
        // moeglich. Der GMT-Timestamp muss in einen FXT-Timestamp konvertiert und dieser als GMT-Timestamp formatiert werden.

        return gmDate($format, fxtTime($time, 'GMT'));
    }


    /**
     * Gibt den FXT-Offset einer Zeit zu GMT und ggf. die beiden jeweils angrenzenden naechsten DST-Transitionsdaten zurueck.
     *
     * @param  int        $time           - GMT-Zeitpunkt (default: aktuelle Zeit)
     * @param  array|null $prevTransition - Wenn angegeben, enthaelt diese Variable nach Rueckkehr ein Array
     *                                      ['time'=>{timestamp}, 'offset'=>{offset}] mit dem GMT-Timestamp des vorherigen
     *                                      Zeitwechsels und dem Offset vor diesem Zeitpunkt.
     * @param  array|null $nextTransition - Wenn angegeben, enthaelt diese Variable nach Rueckkehr ein Array
     *                                      ['time'=>{timestamp}, 'offset'=>{offset}] mit dem GMT-Timestamp des naechsten
     *                                      Zeitwechsels und dem Offset nach diesem Zeitpunkt.
     *
     * @return int - Offset in Sekunden oder NULL, wenn der Zeitpunkt ausserhalb der bekannten Transitionsdaten liegt.
     *               FXT liegt oestlich von GMT, der Offset ist also immer positiv. Es gilt: GMT + Offset = FXT
     *
     *
     * Note: Analog zu date('Z', $time) verhaelt sich diese Funktion, als wenn lokal die (in PHP nicht existierende) Zeitzone 'FXT'
     *       eingestellt worden waere.
     */
    public static function fxtTimezoneOffset($time=null, &$prevTransition=[], &$nextTransition=[]) {
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
            $prevTransition = [];

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
            $nextTransition = [];

            if ($i==-2 || $i >= $transSize-1) {                         // $transitions ist leer oder
                $nextTransition['time'  ] = null;                        // $time liegt in letzter Periode
                $nextTransition['offset'] = null;
            }
            else {
                $nextTransition['time'  ] = $transitions[$i+1]['ts'    ];
                $nextTransition['offset'] = $transitions[$i+1]['offset'] + 7*HOURS;
            }
        }

        // Rueckgabewert definieren
        $offset = null;
        if ($i >= 0)                                                   // $transitions ist nicht leer und
            $offset = $transitions[$i]['offset'] + 7*HOURS;             // $time liegt nicht vor der ersten Periode
        return $offset;
    }


    /**
     * Gibt die Mailadressen aller konfigurierten Signalempfaenger per E-Mail zurueck.
     *
     * @return string[] - Array mit E-Mailadressen
     */
    public static function getMailSignalReceivers() {
        static $addresses = null;

        if (is_null($addresses)) {
            if (!$config=Config::getDefault())
                throw new RuntimeException('Service locator returned invalid default config: '.getType($config));

            $values = $config->get('mail.signalreceivers');
            foreach (explode(',', $values) as $address) {
                if ($address=trim($address))
                    $addresses[] = $address;
            }
            if (!$addresses)
                $addresses = [];
        }
        return $addresses;
    }


    /**
     * Gibt die Rufnummern aller konfigurierten Signalempfaenger per SMS zurueck.
     *
     * @return string[] - Array mit Rufnummern
     */
    public static function getSmsSignalReceivers() {
        static $numbers = null;

        if (is_null($numbers)) {
            if (!$config=Config::getDefault())
                throw new RuntimeException('Service locator returned invalid default config: '.getType($config));

            $values = $config->get('sms.signalreceivers', null);
            foreach (explode(',', $values) as $number) {
                if ($number=trim($number))
                    $numbers[] = $number;
            }
            if (!$numbers)
                $numbers = [];
        }
        return $numbers;
    }


    /**
     * Verschickt eine SMS.
     *
     * @param  string $receiver - Empfaenger (internationales Format)
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

        if (!$config=Config::getDefault())
            throw new RuntimeException('Service locator returned invalid default config: '.getType($config));

        $config   = $config->get('sms.clickatell');
        $username = $config['username'];
        $password = $config['password'];
        $api_id   = $config['api_id'  ];
        $message  = urlEncode($message);
        $url      = 'https://api.clickatell.com/http/sendmsg?user='.$username.'&password='.$password.'&api_id='.$api_id.'&to='.$receiver.'&text='.$message;

        // HTTP-Request erzeugen und ausfuehren
        $request  = HttpRequest ::create()->setUrl($url);
        $options[CURLOPT_SSL_VERIFYPEER] = false;                // das SSL-Zertifikat kann nicht pruefbar oder ungueltig sein
        $response = CurlHttpClient ::create($options)->send($request);
        $status   = $response->getStatus();
        $content  = $response->getContent();
        if ($status != 200) throw new RuntimeException('Unexpected HTTP status code from api.clickatell.com: '.$status.' ('.HttpResponse ::$sc[$status].')');
    }


    /**
     * Gibt die Beschreibung eines Operation-Types zurueck.
     *
     * @param  int $type - Operation-Type
     *
     * @return string - Beschreibung
     */
    public static function operationTypeDescription($type) {
        if (!is_int($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.getType($type));

        static $operationTypes = [
            OP_BUY       => 'Buy'       ,
            OP_SELL      => 'Sell'      ,
            OP_BUYLIMIT  => 'Buy Limit' ,
            OP_SELLLIMIT => 'Sell Limit',
            OP_BUYSTOP   => 'Stop Buy'  ,
            OP_SELLSTOP  => 'Stop Sell' ,
            OP_BALANCE   => 'Balance'   ,
            OP_CREDIT    => 'Credit'    ,
        ];
        if (isSet($operationTypes[$type]))
            return $operationTypes[$type];

        throw new InvalidArgumentException('Invalid parameter $type: '.$type.' (not an operation type)');
    }


    /**
     * Whether or not an integer is a valid order type.
     *
     * @param  int $integer
     *
     * @return bool
     */
    public static function isOrderType($integer) {
        $description = self::orderTypeDescription($integer);
        return ($description !== null);
    }


    /**
     * Whether or not an integer is a long order type.
     *
     * @param  int $integer
     *
     * @return bool
     */
    public static function isLongOrderType($integer) {
        if (is_int($integer)) {
            switch ($integer) {
                case OP_BUY     :
                case OP_BUYLIMIT:
                case OP_BUYSTOP : return true;
            }
        }
        return false;
    }


    /**
     * Whether or not an integer is a short order type.
     *
     * @param  int $integer
     *
     * @return bool
     */
    public static function isShortOrderType($integer) {
        if (is_int($integer)) {
            switch ($integer) {
                case OP_SELL     :
                case OP_SELLLIMIT:
                case OP_SELLSTOP : return true;
            }
        }
        return false;
    }


    /**
     * Return an order type description.
     *
     * @param  int - order type id
     *
     * @return string|null - description or NULL if the parameter is not a valid order type id
     */
    public static function orderTypeDescription($id) {
        if (is_int($id)) {
            switch ($id) {
                case OP_BUY      : return 'Buy';
                case OP_SELL     : return 'Sell';
                case OP_BUYLIMIT : return 'Buy Limit';
                case OP_SELLLIMIT: return 'Sell Limit';
                case OP_BUYSTOP  : return 'Buy Stop';
                case OP_SELLSTOP : return 'Sell Stop';
            }
        }
        return null;
    }


    /**
     * Convert an order type representation to an order type.
     *
     * @param  mixed $value - order type representation
     *
     * @return int - order type or -1 if the value doesn't represent an order type
     */
    public static function strToOrderType($value) {
        if (is_string($value)) {
            if (!strIsNumeric($value)) {
                $value = strToUpper($value);
                if (strStartsWith($value, 'OP_'))
                    $value = strRight($value, -3);
                switch ($value) {
                    case 'BUY'      : return OP_BUY;
                    case 'SELL'     : return OP_SELL;
                    case 'BUYLIMIT' : return OP_BUYLIMIT;
                    case 'SELLLIMIT': return OP_SELLLIMIT;
                    case 'BUYSTOP'  : return OP_BUYSTOP;
                    case 'SELLSTOP' : return OP_SELLSTOP;
                }
                return -1;
            }
            $value = (float)$value;
        }

        if (is_int($value) || is_float($value)) {
            switch ((float)$value) {
                case OP_BUY      : return OP_BUY;
                case OP_SELL     : return OP_SELL;
                case OP_BUYLIMIT : return OP_BUYLIMIT;
                case OP_SELLLIMIT: return OP_SELLLIMIT;
                case OP_BUYSTOP  : return OP_BUYSTOP;
                case OP_SELLSTOP : return OP_SELLSTOP;
            }
            return -1;
        }
        throw new IllegalTypeException('Illegal type of parameter $value: '.getType($value));
    }


    /**
     * Interpretiert die RSX_PRICE_BAR-Daten eines Strings und liest sie in ein Array ein. Die resultierenden Bars werden
     * beim Lesen validiert.
     *
     * @param  string $data   - String mit RSX_PRICE_BAR-Daten
     * @param  string $symbol - Meta-Information fuer eine evt. Fehlermeldung (falls die Daten fehlerhaft sind)
     *
     * @return array - RSX_PRICE_BAR-Daten
     */
    public static function readBarData($data, $symbol) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.getType($data));

        $lenData = strLen($data); if ($lenData % self::BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol.' data: '.$lenData.' (not an even RSX::BAR_SIZE)');
        $offset  = 0;
        $bars    = [];
        $i       = -1;

        while ($offset < $lenData) {
            $i++;
            $bars[] = unpack("@$offset/Vtime/Vopen/Vhigh/Vlow/Vclose/Vticks", $data);
            $offset += self::BAR_SIZE;

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
     * Interpretiert die Bardaten einer RSX-Datei und liest sie in ein Array ein.
     *
     * @param  string $fileName - Name der Datei mit RSX_PRICE_BAR-Daten
     * @param  string $symbol   - Meta-Information fuer eine evt. Fehlermeldung (falls die Daten fehlerhaft sind)
     *
     * @return array - RSX_PRICE_BAR-Daten
     */
    public static function readBarFile($fileName, $symbol) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.getType($fileName));
        return self::readBarData(file_get_contents($fileName), $symbol);
    }


    /**
     * Interpretiert die Bardaten einer komprimierten RSX-Datei und liest sie in ein Array ein.
     *
     * @param  string $fileName - Name der Datei mit RSX_PRICE_BAR-Daten
     *
     * @return array - RSX_PRICE_BAR-Daten
     */
    public static function readCompressedBarFile($fileName) {
        throw new UnimplementedFeatureException(__METHOD__);
    }


    /**
     * Gibt den Offset eines Zeitpunktes innerhalb einer Zeitreihe zurueck.
     *
     * @param  array $series - zu durchsuchende Reihe: Zeiten, Arrays mit dem Feld 'time' oder Objekte mit der Methode getTime()
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn der Offset ausserhalb der Arraygrenzen liegt
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
     * Gibt den Offset der Bar zurueck, die den angegebenen Zeitpunkt exakt abdeckt.
     *
     * @param  array $bars   - zu durchsuchende Bars: RSX_PRICE_BARs oder HISTORY_BARs
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

        $offset = self::findTimeOffset($bars, $time);

        if ($offset < 0) {                                                         // Zeitpunkt liegt nach der juengsten bar[openTime]
            $closeTime = self::periodCloseTime($bars[$size-1]['time'], $period);
            if ($time < $closeTime)                                                 // Zeitpunkt liegt innerhalb der juengsten Bar
                return $size-1;
            return -1;
        }

        if ($bars[$offset]['time'] == $time)                                       // Zeitpunkt liegt exakt auf der jeweiligen Bar
            return $offset;

        if ($offset == 0)                                                          // Zeitpunkt ist aelter die aelteste Bar
            return -1;

        $offset--;
        $closeTime = self::periodCloseTime($bars[$offset]['time'], $period);
        if ($time < $closeTime)                                                    // Zeitpunkt liegt in der vorhergehenden Bar
            return $offset;
        return -1;                                                                 // Zeitpunkt liegt nicht in der vorhergehenden Bar,
    }                                                                             // also Luecke zwischen der vorhergehenden und der
                                                                                                            // folgenden Bar

    /**
     * Gibt den Offset der Bar zurueck, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar, wird der Offset
     * der letzten vorhergehenden Bar zurueckgegeben.
     *
     * @param  array $bars   - zu durchsuchende Bars: RSX_PRICE_BARs oder HISTORY_BARs
     * @param  int   $period - Barperiode
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist aelter als die aelteste Bar)
     */
    public static function findBarOffsetPrevious(array $bars, $period, $time) {
        if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));
        if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
        if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        $size = sizeOf($bars);
        if (!$size)
            return -1;

        $offset = self::findTimeOffset($bars, $time);

        if ($offset < 0)                                                           // Zeitpunkt liegt nach der juengsten bar[openTime]
            return $size-1;

        if ($bars[$offset]['time'] == $time)                                       // Zeitpunkt liegt exakt auf der jeweiligen Bar
            return $offset;
        return $offset - 1;                                                        // Zeitpunkt ist aelter als die Bar desselben Offsets
    }


    /**
     * Gibt den Offset der Bar zurueck, die den angegebenen Zeitpunkt abdeckt. Existiert keine solche Bar, wird der Offset
     * der naechstfolgenden Bar zurueckgegeben.
     *
     * @param  array $bars   - zu durchsuchende Bars: RSX_PRICE_BARs oder HISTORY_BARs
     * @param  int   $period - Barperiode
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist juenger als das Ende der juengsten Bar)
     */
    public static function findBarOffsetNext(array $bars, $period, $time) {
        if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));
        if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
        if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        $size = sizeOf($bars);
        if (!$size)
            return -1;

        $offset = self::findTimeOffset($bars, $time);

        if ($offset < 0) {                                                         // Zeitpunkt liegt nach der juengsten bar[openTime]
            $closeTime = self::periodCloseTime($bars[$size-1]['time'], $period);
            return ($closeTime > $time) ? $size-1 : -1;
        }
        if ($offset == 0)                                                          // Zeitpunkt liegt vor oder exakt auf der ersten Bar
            return 0;

        if ($bars[$offset]['time'] == $time)                                       // Zeitpunkt stimmt mit bar[openTime] ueberein
            return $offset;
        $offset--;                                                                 // Zeitpunkt liegt in der vorherigen oder zwischen der
                                                                                                            // vorherigen und der TimeOffset-Bar
        $closeTime = self::periodCloseTime($bars[$offset]['time'], $period);
        if ($closeTime > $time)                                                    // Zeitpunkt liegt innerhalb dieser vorherigen Bar
            return $offset;
        return ($offset+1 < $bars) ? $offset+1 : -1;                               // Zeitpunkt liegt nach bar[closeTime], also Luecke...
    }                                                                             // zwischen der vorherigen und der folgenden Bar


    /**
     * Gibt die lesbare Konstante eines Timeframe-Codes zurueck.
     *
     * @param  int period - Timeframe-Code bzw. Anzahl der Minuten je Bar
     *
     * @return string
     */
    public static function periodToStr($period) {
        if (!is_int($period)) throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));

        switch ($period) {
            case PERIOD_M1 : return 'PERIOD_M1';       // 1 minute
            case PERIOD_M5 : return 'PERIOD_M5';       // 5 minutes
            case PERIOD_M15: return 'PERIOD_M15';      // 15 minutes
            case PERIOD_M30: return 'PERIOD_M30';      // 30 minutes
            case PERIOD_H1 : return 'PERIOD_H1';       // 1 hour
            case PERIOD_H4 : return 'PERIOD_H4';       // 4 hour
            case PERIOD_D1 : return 'PERIOD_D1';       // 1 day
            case PERIOD_W1 : return 'PERIOD_W1';       // 1 week
            case PERIOD_MN1: return 'PERIOD_MN1';      // 1 month
            case PERIOD_Q1 : return 'PERIOD_Q1';       // 1 quarter
        }
        return (string)$period;
    }


    /**
     * Alias fuer periodToStr()
     *
     * @param  int timeframe
     *
     * @return string
     */
    public static function timeframeToStr($timeframe) {
        return self::periodToStr($timeframe);
    }


    /**
     * Gibt die Beschreibung eines Timeframe-Codes zurueck.
     *
     * @param  int period - Timeframe-Code bzw. Anzahl der Minuten je Bar
     *
     * @return string
     */
    public static function periodDescription($period) {
        if (!is_int($period)) throw new IllegalTypeException('Illegal type of parameter $period: '.getType($period));

        switch ($period) {
            case PERIOD_M1 : return 'M1';      //      1  1 minute
            case PERIOD_M5 : return 'M5';      //      5  5 minutes
            case PERIOD_M15: return 'M15';     //     15  15 minutes
            case PERIOD_M30: return 'M30';     //     30  30 minutes
            case PERIOD_H1 : return 'H1';      //     60  1 hour
            case PERIOD_H4 : return 'H4';      //    240  4 hour
            case PERIOD_D1 : return 'D1';      //   1440  daily
            case PERIOD_W1 : return 'W1';      //  10080  weekly
            case PERIOD_MN1: return 'MN1';     //  43200  monthly
            case PERIOD_Q1 : return 'Q1';      // 129600  3 months (a quarter)
        }
        return (string)$period;
    }


    /**
     * Alias fuer periodDescription()
     *
     * @param  int timeframe
     *
     * @return string
     */
    public static function timeframeDescription($timeframe) {
        return self::periodDescription($timeframe);
    }


    /**
     * Gibt die CloseTime der Periode zurueck, die die angegebene Zeit abdeckt.
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
            $closeTime = $openTime + 1*WEEK;                            // 00:00, naechster Montag
        }
        else /*PERIOD_MN1*/ {
            $m         = (int) gmDate('m', $time);
            $y         = (int) gmDate('Y', $time);
            $closeTime = gmMkTime(0, 0, 0, $m+1, 1, $y);                // 00:00, 1. des naechsten Monats
        }

        return $closeTime;
    }


    /**
     * Erzeugt und verwaltet dynamisch generierte Variablen.
     *
     * Evaluiert und cacht haeufig wiederbenutzte dynamische Variablen an einem zentralen Ort. Vereinfacht die Logik,
     * da die Variablen nicht global gespeichert oder ueber viele Funktionsaufrufe hinweg weitergereicht werden muessen,
     * aber trotzdem nicht bei jeder Verwendung neu ermittelt werden brauchen.
     *
     * @param  string $id     - eindeutiger Bezeichner der Variable
     * @param  string $symbol - Symbol oder NULL
     * @param  int    $time   - Timestamp oder NULL
     *
     * @return string - Variable
     */
    public static function getVar($id, $symbol=null, $time=null) {
        static $varCache = [];
        if (array_key_exists(($key=$id.'|'.$symbol.'|'.$time), $varCache))
            return $varCache[$key];

        if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
        if (isSet($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));
        if (isSet($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.getType($time));

        if ($id == 'rsxDirDate') {                   // $yyyy/$mm/$dd                                                   // lokales Pfad-Datum
            if (!$time)   throw new InvalidArgumentException('Invalid parameter $time: '.$time);
            $result = gmDate('Y/m/d', $time);
        }
        else if ($id == 'rsxDir') {                  // $dataDirectory/history/rsx/$type/$symbol/$rsxDirDate            // lokales Verzeichnis
            if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
            static $dataDirectory; if (!$dataDirectory)
            $dataDirectory = Config::getDefault()->get('app.dir.data');
            $type          = self::$symbols[$symbol]['type'];
            $rsxDirDate    = self::{__FUNCTION__}('rsxDirDate', null, $time);
            $result        = $dataDirectory.'/history/rsx/'.$type.'/'.$symbol.'/'.$rsxDirDate;
        }
        else if ($id == 'rsxFile.M1.raw') {          // $rsxDir/M1.myfx                                                 // RSX-M1-Datei ungepackt
            $rsxDir = self::{__FUNCTION__}('rsxDir' , $symbol, $time);
            $result = $rsxDir.'/M1.myfx';
        }
        else if ($id == 'rsxFile.M1.compressed') {   // $rsxDir/M1.rar                                                  // RSX-M1-Datei gepackt
            $rsxDir = self::{__FUNCTION__}('rsxDir', $symbol, $time);
            $result = $rsxDir.'/M1.rar';
        }
        else throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');

        $varCache[$key] = $result;
        (sizeof($varCache) > ($maxSize=256)) && array_shift($varCache)/* && echoPre('var cache size limit of '.$maxSize.' hit')*/;

        return $result;
    }
}


/**
 * Workaround for PHP's missing static initializers.
 */
RSX::$symbols = [
    'AUDUSD' => ['group'=>'forex'    , 'name'=>'AUDUSD', 'description'=>'Australian Dollar vs US Dollar'             , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-08-03 21:00:00 GMT'), 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'EURCHF' => ['group'=>'forex'    , 'name'=>'EURCHF', 'description'=>'Euro vs Swiss Franc'                        , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'EURUSD' => ['group'=>'forex'    , 'name'=>'EURUSD', 'description'=>'Euro vs US Dollar'                          , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'GBPUSD' => ['group'=>'forex'    , 'name'=>'GBPUSD', 'description'=>'Great Britain Pound vs US Dollar'           , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'NZDUSD' => ['group'=>'forex'    , 'name'=>'NZDUSD', 'description'=>'New Zealand Dollar vs US Dollar'            , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-08-03 21:00:00 GMT'), 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'USDCAD' => ['group'=>'forex'    , 'name'=>'USDCAD', 'description'=>'US Dollar vs Canadian Dollar'               , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-08-03 21:00:00 GMT'), 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'USDCHF' => ['group'=>'forex'    , 'name'=>'USDCHF', 'description'=>'US Dollar vs Swiss Franc'                   , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'USDJPY' => ['group'=>'forex'    , 'name'=>'USDJPY', 'description'=>'US Dollar vs Japanese Yen'                  , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>['ticks'=>strToTime('2003-05-04 21:00:00 GMT'), 'M1'=>strToTime('2003-05-04 00:00:00 GMT')], 'provider'=>'dukascopy'],
    'USDNOK' => ['group'=>'forex'    , 'name'=>'USDNOK', 'description'=>'US Dollar vs Norwegian Krone'               , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-08-04 00:00:00 GMT'), 'M1'=>strToTime('2003-08-05 00:00:00 GMT')], 'provider'=>'dukascopy'],     // TODO: M1-Start ist der 04.08.2003
    'USDSEK' => ['group'=>'forex'    , 'name'=>'USDSEK', 'description'=>'US Dollar vs Swedish Krona'                 , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2003-08-04 00:00:00 GMT'), 'M1'=>strToTime('2003-08-05 00:00:00 GMT')], 'provider'=>'dukascopy'],     // TODO: M1-Start ist der 04.08.2003
    'USDSGD' => ['group'=>'forex'    , 'name'=>'USDSGD', 'description'=>'US Dollar vs Singapore Dollar'              , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('2004-11-16 18:00:00 GMT'), 'M1'=>strToTime('2004-11-17 00:00:00 GMT')], 'provider'=>'dukascopy'],     // TODO: M1-Start ist der 16.11.2004
    'USDZAR' => ['group'=>'forex'    , 'name'=>'USDZAR', 'description'=>'US Dollar vs South African Rand'            , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>strToTime('1997-10-13 18:00:00 GMT'), 'M1'=>strToTime('1997-10-14 00:00:00 GMT')], 'provider'=>'dukascopy'],     // TODO: M1-Start ist der 13.11.1997

    'XAUUSD' => ['group'=>'metals'   , 'name'=>'XAUUSD', 'description'=>'Gold vs US Dollar'                          , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>['ticks'=>strToTime('2003-05-05 00:00:00 GMT'), 'M1'=>strToTime('1999-09-02 00:00:00 GMT')], 'provider'=>'dukascopy'],     // TODO: M1-Start ist der 01.09.1999

    'AUDLFX' => ['group'=>'synthetic', 'name'=>'AUDLFX', 'description'=>'LiteForex scaled-down Australian Dollar FX6 index'  , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'CADLFX' => ['group'=>'synthetic', 'name'=>'CADLFX', 'description'=>'LiteForex scaled-down Canadian Dollar FX6 index'    , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'CHFLFX' => ['group'=>'synthetic', 'name'=>'CHFLFX', 'description'=>'LiteForex scaled-down Swiss Franc FX6 index'        , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'EURLFX' => ['group'=>'synthetic', 'name'=>'EURLFX', 'description'=>'LiteForex scaled-down Euro FX6 index'               , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'GBPLFX' => ['group'=>'synthetic', 'name'=>'GBPLFX', 'description'=>'LiteForex scaled-down Great Britain Pound FX6 index', 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'JPYLFX' => ['group'=>'synthetic', 'name'=>'JPYLFX', 'description'=>'LiteForex scaled-down Japanese Yen FX6 index'       , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'NZDLFX' => ['group'=>'synthetic', 'name'=>'NZDLFX', 'description'=>'LiteForex alias of New Zealand Dollar FX7 index'    , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'USDLFX' => ['group'=>'synthetic', 'name'=>'USDLFX', 'description'=>'LiteForex scaled-down US Dollar FX6 index'          , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],

    'AUDFX6' => ['group'=>'synthetic', 'name'=>'AUDFX6', 'description'=>'Australian Dollar FX6 index'                        , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'CADFX6' => ['group'=>'synthetic', 'name'=>'CADFX6', 'description'=>'Canadian Dollar FX6 index'                          , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'CHFFX6' => ['group'=>'synthetic', 'name'=>'CHFFX6', 'description'=>'Swiss Franc FX6 index'                              , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'EURFX6' => ['group'=>'synthetic', 'name'=>'EURFX6', 'description'=>'Euro FX6 index'                                     , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'GBPFX6' => ['group'=>'synthetic', 'name'=>'GBPFX6', 'description'=>'Great Britain Pound FX6 index'                      , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'JPYFX6' => ['group'=>'synthetic', 'name'=>'JPYFX6', 'description'=>'Japanese Yen FX6 index'                             , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'USDFX6' => ['group'=>'synthetic', 'name'=>'USDFX6', 'description'=>'US Dollar FX6 index'                                , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],

    'AUDFX7' => ['group'=>'synthetic', 'name'=>'AUDFX7', 'description'=>'Australian Dollar FX7 index'                        , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'CADFX7' => ['group'=>'synthetic', 'name'=>'CADFX7', 'description'=>'Canadian Dollar FX7 index'                          , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'CHFFX7' => ['group'=>'synthetic', 'name'=>'CHFFX7', 'description'=>'Swiss Franc FX7 index'                              , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'EURFX7' => ['group'=>'synthetic', 'name'=>'EURFX7', 'description'=>'Euro FX7 index'                                     , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'GBPFX7' => ['group'=>'synthetic', 'name'=>'GBPFX7', 'description'=>'Great Britain Pound FX7 index'                      , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'JPYFX7' => ['group'=>'synthetic', 'name'=>'JPYFX7', 'description'=>'Japanese Yen FX7 index'                             , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'NOKFX7' => ['group'=>'synthetic', 'name'=>'NOKFX7', 'description'=>'Norwegian Krone FX7 index'                          , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-05 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'NZDFX7' => ['group'=>'synthetic', 'name'=>'NZDFX7', 'description'=>'New Zealand Dollar FX7 index'                       , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'SEKFX7' => ['group'=>'synthetic', 'name'=>'SEKFX7', 'description'=>'Swedish Krona FX7 index'                            , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-05 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'SGDFX7' => ['group'=>'synthetic', 'name'=>'SGDFX7', 'description'=>'Singapore Dollar FX7 index'                         , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2004-11-16 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'USDFX7' => ['group'=>'synthetic', 'name'=>'USDFX7', 'description'=>'US Dollar FX7 index'                                , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'ZARFX7' => ['group'=>'synthetic', 'name'=>'ZARFX7', 'description'=>'South African Rand FX7 index'                       , 'digits'=>5, 'pip'=>0.0001, 'point'=>0.00001, 'priceFormat'=>".4'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-03 00:00:00 GMT')], 'provider'=>'rsx'      ],

    'EURX'   => ['group'=>'synthetic', 'name'=>'EURX'  , 'description'=>'ICE Euro Futures index'                             , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-04 00:00:00 GMT')], 'provider'=>'rsx'      ],
    'USDX'   => ['group'=>'synthetic', 'name'=>'USDX'  , 'description'=>'ICE US Dollar Futures index'                        , 'digits'=>3, 'pip'=>0.01  , 'point'=>0.001  , 'priceFormat'=>".2'", 'historyStart'=>['ticks'=>null                                , 'M1'=>strToTime('2003-08-04 00:00:00 GMT')], 'provider'=>'rsx'      ],
];
