<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\ministruts\core\CObject;
use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\exception\InvalidValueException;

use function rosasurfer\ministruts\strLeft;
use function rosasurfer\ministruts\strLeftTo;
use function rosasurfer\ministruts\strRight;


/**
 * HistoryHeader eines HistoryFiles ("*.hst")
 */
class HistoryHeader extends CObject {


    /** @var int - sizeof(struct HISTORY_HEADER) */
    const SIZE = 148;

    /** @var int */
    protected $format;

    /** @var string */
    protected $copyright = '';

    /** @var string */
    protected $symbol;

    /** @var int */
    protected $period;

    /** @var int */
    protected $digits;

    /** @var int */
    protected $syncMarker = 0;

    /** @var int */
    protected $lastSyncTime = 0;


    /**
     * @return int
     */
    public function getFormat() {
        return $this->format;
    }

    /**
     * @return string
     */
    public function getCopyright() {
        return $this->copyright;
    }

    /**
     * @return string
     */
    public function getSymbol() {
        return $this->symbol;
    }

    /**
     * @return int
     */
    public function getPeriod() {
        return $this->period;
    }

    /**
     * @return int
     */
    public function getDigits() {
        return $this->digits;
    }

    /**
     * @return int
     */
    public function getSyncMarker() {
        return $this->syncMarker;
    }

    /**
     * @return int
     */
    public function getLastSyncTime() {
        return $this->lastSyncTime;
    }


    /**
     * Formatbeschreibung eines struct HISTORY_HEADER.
     *
     * @see  https://github.com/rosasurfer/mt4-expander/blob/master/header/struct/mt4/HistoryHeader.h
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
        else throw new InvalidValueException('Invalid number of arguments: '.$argc);
    }


    /**
     * Constructor 1
     *
     * Erzeugt eine neue Instanz anhand der uebergebenen Parameter.
     *
     * @param  int     $format       - unterstuetzte Formate: 400 und 401
     * @param  ?string $copyright
     * @param  string  $symbol
     * @param  int     $period
     * @param  int     $digits
     * @param  ?int    $syncMarker
     * @param  ?int    $lastSyncTime
     *
     * @return void
     */
    private function __construct_1($format, $copyright='', $symbol, $period, $digits, $syncMarker=0, $lastSyncTime=0) {
        Assert::int($format, '$format');
        if ($format!=400 && $format!=401)             throw new MetaTraderException('version.unsupported: Invalid parameter $format: '.$format.' (can be 400 or 401)');
        if (!isset($copyright)) $copyright = '';
        Assert::string($copyright, '$copyright');
        $copyright = strLeft($copyright, 63);
        Assert::string($symbol, '$symbol');
        if (!strlen($symbol))                         throw new InvalidValueException('Invalid parameter $symbol: ""');
        if (strlen($symbol) > MT4::MAX_SYMBOL_LENGTH) throw new InvalidValueException('Invalid parameter $symbol: "'.$symbol.'" (max '.MT4::MAX_SYMBOL_LENGTH.' characters)');
        Assert::int($period, '$period');
        if ($period <= 0)                             throw new InvalidValueException('Invalid parameter $period: '.$period);
        Assert::int($digits, '$digits');
        if ($digits < 0)                              throw new InvalidValueException('Invalid parameter $digits: '.$digits);
        if (!isset($syncMarker)) $syncMarker = 0;
        Assert::int($syncMarker, '$syncMarker');
        if ($syncMarker < 0)                          throw new InvalidValueException('Invalid parameter $syncMarker: '.$syncMarker);
        if (!isset($lastSyncTime)) $lastSyncTime = 0;
        Assert::int($lastSyncTime, '$lastSyncTime');
        if ($lastSyncTime < 0)                        throw new InvalidValueException('Invalid parameter $lastSyncTime: '.$lastSyncTime);

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
     *
     * @return void
     */
    private function __construct_2($data) {
        Assert::string($data);
        if (strlen($data) != self::SIZE) throw new InvalidValueException('Invalid length of parameter $data: '.strlen($data).' (not '.__CLASS__.'::SIZE)');

        $header = unpack(static::unpackFormat(), $data);
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
     *
     * @return void
     */
    public function setLastSyncTime($time) {
        Assert::int($time);
        if ($time < 0) throw new InvalidValueException('Invalid parameter $time: '.$time);

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
            foreach ($lines as &$line) {
                $line = strLeftTo($line, '//');                          // Kommentare entfernen
            };
            unset($line);
            $format = join('', $lines);

            // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
            if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

            $format = preg_replace('/\s/', '', $format);                // White-Space entfernen
            if ($format[0] == '/') $format = strRight($format, -1);     // ersten Format-Separator entfernen
        }
        return $format;
    }
}
