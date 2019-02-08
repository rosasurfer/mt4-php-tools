<?php
namespace rosasurfer\rt\metatrader;

use rosasurfer\core\Object;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * HistoryHeader eines HistoryFiles ("*.hst")
 */
class HistoryHeader extends Object {

    /**
     * Struct-Size eines HistoryHeaders
     */
    const SIZE = 148;

    protected /*int   */ $format;
    protected /*string*/ $copyright    = '';
    protected /*string*/ $symbol;
    protected /*int   */ $period;
    protected /*int   */ $digits;
    protected /*int   */ $syncMarker   = 0;
    protected /*int   */ $lastSyncTime = 0;


    // Getter
    public function getFormat()       { return $this->format;       }
    public function getCopyright()    { return $this->copyright;    }
    public function getSymbol()       { return $this->symbol;       }
    public function getPeriod()       { return $this->period;       }
    public function getTimeframe()    { return $this->getPeriod();  }    // Alias
    public function getDigits()       { return $this->digits;       }
    public function getSyncMarker()   { return $this->syncMarker;   }
    public function getLastSyncTime() { return $this->lastSyncTime; }


    /**
     * Formatbeschreibung eines struct HISTORY_HEADER.
     *
     * @see  Definition in MT4Expander.dll::Expander.h
     * @see  self::unpackFormat() zum Verwenden als unpack()-Formatstring
     */
    private static $formatStr = '
        /V    format            // uint
        /a64  copyright         // szchar
        /a12  symbol            // szchar
        /V    period            // uint
        /V    digits            // uint
        /V    syncMarker        // datetime
        /V    lastSyncTime      // datetime
        /x52  reserved
    ';


    /**
     * Ueberladener Constructor.
     *
     * Signaturen:
     * -----------
     * new HistoryHeader(int $format, string $copyright, string $symbol, int $period, int $digits, int $syncMarker, int $lastSyncTime)
     * new HistoryHeader(string $data)
     */
    public function __construct($arg1=null, $arg2=null, $arg3=null, $arg4=null, $arg5=null, $arg6=null, $arg7=null) {
        $argc = func_num_args();
        if      ($argc == 7) $this->__construct_1($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7);
        else if ($argc == 1) $this->__construct_2($arg1);
        else throw new InvalidArgumentException('Invalid number of arguments: '.$argc);
    }


    /**
     * Constructor 1
     *
     * Erzeugt eine neue Instanz anhand der uebergebenen Parameter.
     *
     * @param  int    $format       - unterstuetzte Formate: 400 und 401
     * @param  string $copyright
     * @param  string $symbol
     * @param  int    $period
     * @param  int    $digits
     * @param  int    $syncMarker
     * @param  int    $lastSyncTime
     */
    private function __construct_1($format, $copyright, $symbol, $period, $digits, $syncMarker, $lastSyncTime) {
        if (!is_int($format))                         throw new IllegalTypeException('Illegal type of parameter $format: '.gettype($format));
        if ($format!=400 && $format!=401)             throw new MetaTraderException('version.unsupported: Invalid parameter $format: '.$format.' (can be 400 or 401)');
        if (!isset($copyright)) $copyright = '';
        if (!is_string($copyright))                   throw new IllegalTypeException('Illegal type of parameter $copyright: '.gettype($copyright));
        $copyright = strLeft($copyright, 63);
        if (!is_string($symbol))                      throw new IllegalTypeException('Illegal type of parameter $symbol: '.gettype($symbol));
        if (!strlen($symbol))                         throw new InvalidArgumentException('Invalid parameter $symbol: ""');
        if (strlen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidArgumentException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
        if (!is_int($period))                         throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period <= 0)                             throw new InvalidArgumentException('Invalid parameter $period: '.$period);
        if (!is_int($digits))                         throw new IllegalTypeException('Illegal type of parameter $digits: '.gettype($digits));
        if ($digits < 0)                              throw new InvalidArgumentException('Invalid parameter $digits: '.$digits);
        if (!isset($syncMarker)) $syncMarker = 0;
        if (!is_int($syncMarker))                     throw new IllegalTypeException('Illegal type of parameter $syncMarker: '.gettype($syncMarker));
        if ($syncMarker < 0)                          throw new InvalidArgumentException('Invalid parameter $syncMarker: '.$syncMarker);
        if (!isset($lastSyncTime)) $lastSyncTime = 0;
        if (!is_int($lastSyncTime))                   throw new IllegalTypeException('Illegal type of parameter $lastSyncTime: '.gettype($lastSyncTime));
        if ($lastSyncTime < 0)                        throw new InvalidArgumentException('Invalid parameter $lastSyncTime: '.$lastSyncTime);

        // Daten speichern
        $this->format       = $format;
        $this->copyright    = $copyright;
        $this->symbol       = $symbol;
        $this->period       = $period;
        $this->digits       = $digits;
        $this->syncMarker   = $syncMarker;
        $this->lastSyncTime = $lastSyncTime;
    }


    /**
     * Constructor 2
     *
     * Erzeugt eine neue Instanz anhand eines binaer gespeicherten struct HISTORY_HEADER.
     *
     * @param  string $data - gespeichertes struct HISTORY_HEADER
     */
    private function __construct_2($data) {
        if (!is_string($data))                                throw new IllegalTypeException('Illegal type of parameter $data: '.gettype($data));
        if (strlen($data) != self::SIZE)                      throw new InvalidArgumentException('Invalid length of parameter $data: '.strlen($data).' (not '.__CLASS__.'::SIZE)');

        $header = unpack(self::unpackFormat(), $data);
        if ($header['format']!=400 && $header['format']!=401) throw new MetaTraderException('version.unsupported: Invalid or unsupported history format version: '.$header['format']);

        // Daten speichern
        $this->format       = $header['format'      ];
        $this->copyright    = $header['copyright'   ];
        $this->symbol       = $header['symbol'      ];
        $this->period       = $header['period'      ];
        $this->digits       = $header['digits'      ];
        $this->syncMarker   = $header['syncMarker'  ];
        $this->lastSyncTime = $header['lastSyncTime'];
    }


    /**
     * Setzt den Zeitpunkt der letzten Synchronisation in diesem Header.
     *
     * @param  int $time
     */
    public function setLastSyncTime($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if ($time < 0)      throw new InvalidArgumentException('Invalid parameter $time: '.$time);

        $this->lastSyncTime = $time;
    }


    /**
     * Gibt den Formatstring zum Packen eines struct HISTORY_HEADER zurueck.
     *
     * @return string - pack()-Formatstring
     */
    public static function packFormat() {
        static $format = null;

        if (is_null($format)) {
            $lines = explode("\n", self::$formatStr);
            foreach ($lines as &$line) {
                $line = strLeftTo($line, '//');                          // Kommentare entfernen
            }; unset($line);

            $values = explode('/', join('', $lines));                   // in Format-Codes zerlegen

            foreach ($values as $i => &$value) {
                $value = trim($value);
                $value = strLeftTo($value, ' ');                         // dem Code folgende Bezeichner entfernen
                if (!strlen($value))
                    unset($values[$i]);
            }; unset($value);
            $format = join('', $values);
        }
        return $format;
    }


    /**
     * Gibt den Formatstring zum Entpacken eines struct HISTORY_HEADER zurueck.
     *
     * @return string - unpack()-Formatstring
     */
    public static function unpackFormat() {
        static $format = null;

        if (is_null($format)) {
            $lines = explode("\n", self::$formatStr);
            foreach ($lines as $i => &$line) {
                $line = strLeftTo($line, '//');                          // Kommentare entfernen
            }; unset($line);
            $format = join('', $lines);

            // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
            if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

            $format = preg_replace('/\s/', '', $format);                // White-Space entfernen
            if ($format[0] == '/') $format = strRight($format, -1);     // ersten Format-Separator entfernen
        }
        return $format;
    }
}
