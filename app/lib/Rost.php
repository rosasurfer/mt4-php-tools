<?php
namespace rosasurfer\rt\lib;

use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\net\http\CurlHttpClient;
use rosasurfer\net\http\HttpRequest;
use rosasurfer\net\http\HttpResponse;

use rosasurfer\rt\lib\metatrader\MT4;
use rosasurfer\rt\model\RosaSymbol;

use const rosasurfer\rt\OP_BALANCE;
use const rosasurfer\rt\OP_BUY;
use const rosasurfer\rt\OP_BUYLIMIT;
use const rosasurfer\rt\OP_BUYSTOP;
use const rosasurfer\rt\OP_CREDIT;
use const rosasurfer\rt\OP_SELL;
use const rosasurfer\rt\OP_SELLLIMIT;
use const rosasurfer\rt\OP_SELLSTOP;
use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PERIOD_W1;


/**
 * Rosatrader related functionality
 *
 *                                             size        offset      description
 * struct ROST_PRICE_BAR {                     ----        ------      ------------------------------------------------
 *    uint time;                                 4            0        FXT timestamp (seconds since 01.01.1970 FXT)
 *    uint open;                                 4            4        in point
 *    uint high;                                 4            8        in point
 *    uint low;                                  4           12        in point
 *    uint close;                                4           16        in point
 *    uint ticks;                                4           20
 * };                                    = 24 byte
 *
 *                                             size        offset      description
 * struct ROST_PIP_BAR {                       ----        ------      ------------------------------------------------
 *    uint   time;                               4            0        FXT timestamp (seconds since 01.01.1970 FXT)
 *    double open;                               8            4        in pip
 *    double high;                               8           12        in pip
 *    double low;                                8           20        in pip
 *    double close;                              8           28        in pip
 * };                                    = 36 byte
 *
 *                                             size        offset      description
 * struct ROST_TICK {                          ----        ------      ------------------------------------------------
 *    uint timeDelta;                            4            0        milliseconds since beginning of the hour
 *    uint bid;                                  4            4        in point
 *    uint ask;                                  4            8        in point
 * };                                    = 12 byte
 */
class Rost extends StaticClass {


    /**
     * struct size in bytes of a ROST_PRICE_BAR (format of Rosatrader history files "{PERIOD}.bin")
     */
    const BAR_SIZE = 24;

    /**
     * struct size in bytes of an RT tick (format of Rosatrader tick files "{HOUR}h_ticks.bin")
     */
    const TICK_SIZE = 12;


    /**
     * Gibt die Mailadressen aller konfigurierten Signalempfaenger per E-Mail zurueck.
     *
     * @return string[] - Array mit E-Mailadressen
     */
    public static function getMailSignalReceivers() {
        static $addresses = null;

        if (is_null($addresses)) {
            $values = self::di('config')->get('mail.signalreceivers', '');
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
            $values = self::di('config')->get('sms.signalreceivers', '');
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
        if (!is_string($receiver))   throw new IllegalTypeException('Illegal type of parameter $receiver: '.gettype($receiver));
        $receiver = trim($receiver);
        if (strStartsWith($receiver, '+' )) $receiver = substr($receiver, 1);
        if (strStartsWith($receiver, '00')) $receiver = substr($receiver, 2);
        if (!ctype_digit($receiver)) throw new InvalidArgumentException('Invalid argument $receiver: "'.$receiver.'"');

        if (!is_string($message))    throw new IllegalTypeException('Illegal type of parameter $message: '.gettype($message));
        $message = trim($message);
        if ($message == '')          throw new InvalidArgumentException('Invalid argument $message: "'.$message.'"');

        $config   = self::di('config')['sms.clickatell'];
        $username = $config['username'];
        $password = $config['password'];
        $api_id   = $config['api_id'  ];
        $message  = urlencode($message);
        $url      = 'https://api.clickatell.com/http/sendmsg?user='.$username.'&password='.$password.'&api_id='.$api_id.'&to='.$receiver.'&text='.$message;

        // HTTP-Request erzeugen und ausfuehren
        $request  = new HttpRequest($url);
        $options[CURLOPT_SSL_VERIFYPEER] = false;                // das SSL-Zertifikat kann nicht pruefbar oder ungueltig sein
        $response = (new CurlHttpClient($options))->send($request);
        $status   = $response->getStatus();
        $content  = $response->getContent();
        if ($status != 200) throw new RuntimeException('Unexpected HTTP status code from api.clickatell.com: '.$status.' ('.HttpResponse::$sc[$status].')');
    }


    /**
     * Gibt die Beschreibung eines Operation-Types zurueck.
     *
     * @param  int $type - Operation-Type
     *
     * @return string - Beschreibung
     */
    public static function operationTypeDescription($type) {
        if (!is_int($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));

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
        if (isset($operationTypes[$type]))
            return $operationTypes[$type];

        throw new InvalidArgumentException('Invalid parameter $type: '.$type.' (not an operation type)');
    }


    /**
     * Whether an integer is a valid order type.
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
     * Whether an integer is a long order type.
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
     * Whether an integer is a short order type.
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
     * @param  int $id - order type id
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
                $value = strtoupper($value);
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
        throw new IllegalTypeException('Illegal type of parameter $value: '.gettype($value));
    }


    /**
     * Interpretiert die ROST_PRICE_BAR-Daten eines Strings und liest sie in ein Array ein. Die resultierenden Bars werden
     * beim Lesen validiert.
     *
     * @param  string $data   - String mit ROST_PRICE_BAR-Daten
     * @param  string $symbol - Meta-Information fuer eine evt. Fehlermeldung (falls die Daten fehlerhaft sind)
     *
     * @return array - ROST_PRICE_BAR-Daten
     */
    public static function readBarData($data, $symbol) {
        if (!is_string($data)) throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));

        $lenData = strlen($data); if ($lenData % self::BAR_SIZE) throw new RuntimeException('Odd length of passed '.$symbol.' data: '.$lenData.' (not an even Rost::BAR_SIZE)');
        $bars = [];

        for ($offset=0; $offset < $lenData; $offset += self::BAR_SIZE) {
            $bars[] = unpack("@$offset/Vtime/Vopen/Vhigh/Vlow/Vclose/Vticks", $data);
        }
        return $bars;
    }


    /**
     * Interpretiert die Bardaten einer RT-Datei und liest sie in ein Array ein.
     *
     * @param  string $fileName - Name der Datei mit ROST_PRICE_BAR-Daten
     * @param  string $symbol   - Meta-Information fuer eine evt. Fehlermeldung (falls die Daten fehlerhaft sind)
     *
     * @return array - ROST_PRICE_BAR-Daten
     */
    public static function readBarFile($fileName, $symbol) {
        if (!is_string($fileName)) throw new IllegalTypeException('Illegal type of parameter $fileName: '.gettype($fileName));
        return self::readBarData(file_get_contents($fileName), $symbol);
    }


    /**
     * Interpretiert die Bardaten einer komprimierten RT-Datei und liest sie in ein Array ein.
     *
     * @param  string $fileName - Name der Datei mit ROST_PRICE_BAR-Daten
     *
     * @return array - ROST_PRICE_BAR-Daten
     */
    public static function readCompressedBarFile($fileName) {
        throw new UnimplementedFeatureException(__METHOD__);
    }


    /**
     * Gibt den Offset eines Zeitpunktes innerhalb einer Zeitreihe zurueck.
     *
     * @param  array $series - zu durchsuchende Reihe: Arrays mit dem Feld "time"
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn der Offset ausserhalb der Arraygrenzen liegt
     */
    public static function findTimeOffset(array $series, $time) {
        if (!is_int($time))              throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        $size = sizeof($series); if (!$size) return -1;
        if (!is_array($series[0]))       throw new IllegalTypeException('Illegal type of element $series[0]: '.gettype($series[0]));
        if (!isset($series[0]['time']))  throw new InvalidArgumentException('Invalid parameter $series[0]: '.gettype($series[0].' (no index "time")'));
        if (!is_int($series[0]['time'])) throw new IllegalTypeException('Illegal type of element $series[0][time]: '.gettype($series[0]['time']));

        $i     = -1;
        $iFrom =  0;
        $iTo   = $size-1; if ($series[$iTo]['time'] < $time) return -1;

        while (true) {                                              // Zeitfenster von Beginn- und Endbar rekursiv bis zum
            if ($series[$iFrom]['time'] >= $time) {                 // gesuchten Zeitpunkt verkleinern
                $i = $iFrom;
                break;
            }
            if ($series[$iTo]['time']==$time || $size==2) {
                $i = $iTo;
                break;
            }
            $midSize = (int) ceil($size/2);                         // Fenster halbieren
            $iMid    = $iFrom + $midSize - 1;
            if ($series[$iMid]['time'] <= $time) $iFrom = $iMid;
            else                                 $iTo   = $iMid;
            $size = $iTo - $iFrom + 1;
        }
        return $i;
    }


    /**
     * Gibt den Offset der Bar zurueck, die den angegebenen Zeitpunkt exakt abdeckt.
     *
     * @param  array $bars   - zu durchsuchende Bars: ROST_PRICE_BARs oder HISTORY_BARs
     * @param  int   $period - Barperiode
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert
     */
    public static function findBarOffset(array $bars, $period, $time) {
        if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
        if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        $size = sizeof($bars);
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
     * @param  array $bars   - zu durchsuchende Bars: ROST_PRICE_BARs oder HISTORY_BARs
     * @param  int   $period - Barperiode
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist aelter als die aelteste Bar)
     */
    public static function findBarOffsetPrevious(array $bars, $period, $time) {
        if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
        if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        $size = sizeof($bars);
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
     * @param  array $bars   - zu durchsuchende Bars: ROST_PRICE_BARs oder HISTORY_BARs
     * @param  int   $period - Barperiode
     * @param  int   $time   - Zeitpunkt
     *
     * @return int - Offset oder -1, wenn keine solche Bar existiert (der Zeitpunkt ist juenger als das Ende der juengsten Bar)
     */
    public static function findBarOffsetNext(array $bars, $period, $time) {
        if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');
        if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        $size = sizeof($bars);
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
     * Gibt die CloseTime der Periode zurueck, die die angegebene Zeit abdeckt.
     *
     * @param  int  $time   - Zeitpunkt
     * @param  int  $period - Periode
     *
     * @return int - Zeit
     */
    public static function periodCloseTime($time, $period) {
        if (!is_int($time))                throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if (!is_int($period))              throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if (!MT4::isStdTimeframe($period)) throw new InvalidArgumentException('Invalid parameter $period: '.$period.' (not a standard timeframe)');

        if ($period <= PERIOD_D1) {
            $openTime  = $time - $time%$period*MINUTES;
            $closeTime = $openTime + $period*MINUTES;
        }
        else if ($period == PERIOD_W1) {
            $dow       = (int) gmdate('w', $time);
            $openTime  = $time - $time%DAY - (($dow+6)%7)*DAYS;         // 00:00, Montag
            $closeTime = $openTime + 1*WEEK;                            // 00:00, naechster Montag
        }
        else /*PERIOD_MN1*/ {
            $m         = (int) gmdate('m', $time);
            $y         = (int) gmdate('Y', $time);
            $closeTime = gmmktime(0, 0, 0, $m+1, 1, $y);                // 00:00, 1. des naechsten Monats
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

        if (!is_string($id))                       throw new IllegalTypeException('Illegal type of parameter $id: '.gettype($id));
        if (isset($symbol) && !is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
        if (isset($time) && !is_int($time))        throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        static $dataDir; !$dataDir && $dataDir = self::di('config')['app.dir.data'];

        if ($id == 'rtDirDate') {                       // $yyyy/$mm/$dd                                                // lokales Pfad-Datum
            if (!$time) throw new InvalidArgumentException('Invalid parameter $time: '.$time);
            $result = gmdate('Y/m/d', $time);
        }
        else if ($id == 'rtDir') {                      // $dataDir/history/rosatrader/$type/$symbol/$rtDirDate         // lokales Verzeichnis
            if (!$symbol) throw new InvalidArgumentException('Invalid parameter $symbol: '.$symbol);
            $type      = RosaSymbol::dao()->getByName($symbol)->getType();
            $rtDirDate = self::{__FUNCTION__}('rtDirDate', null, $time);
            $result    = $dataDir.'/history/rosatrader/'.$type.'/'.$symbol.'/'.$rtDirDate;
        }
        else if ($id == 'rtFile.M1.raw') {              // $rtDir/M1.bin                                                // RT-M1-Datei ungepackt
            $rtDir  = self::{__FUNCTION__}('rtDir' , $symbol, $time);
            $result = $rtDir.'/M1.bin';
        }
        else if ($id == 'rtFile.M1.compressed') {       // $rtDir/M1.rar                                                // RT-M1-Datei gepackt
            $rtDir  = self::{__FUNCTION__}('rtDir', $symbol, $time);
            $result = $rtDir.'/M1.rar';
        }
        else throw new InvalidArgumentException('Unknown variable identifier "'.$id.'"');

        $varCache[$key] = $result;
        (sizeof($varCache) > ($maxSize=256)) && array_shift($varCache)/* && echoPre('var cache size limit of '.$maxSize.' hit')*/;

        return $result;
    }


    /**
     * Convert an absolute file path to a project-relative one.
     *
     * @param  string $path
     *
     * @return string
     */
    public static function relativePath($path) {
        if (!is_string($path)) throw new IllegalTypeException('Illegal type of parameter $path: '.gettype($path));
        $_path = str_replace('\\', '/', $path);

        static $root, $realRoot, $data, $realData;
        if (!$root) {
            $config   = self::di('config');
            $root     = str_replace('\\', '/', $config['app.dir.root'].'/');
            $realRoot = str_replace('\\', '/', realpath($root).'/');
            $data     = str_replace('\\', '/', $config['app.dir.data'].'/');
            $realData = str_replace('\\', '/', realpath($data).'/');
        }

        if (strStartsWith($_path, $root))     return               strRightFrom($_path, $root);
        if (strStartsWith($_path, $realRoot)) return               strRightFrom($_path, $realRoot);
        if (strStartsWith($_path, $data))     return '{data-dir}/'.strRightFrom($_path, $data);
        if (strStartsWith($_path, $realData)) return '{data-dir}/'.strRightFrom($_path, $realData);

        return $path;
    }
}
