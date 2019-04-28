<?php
namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\RuntimeException;

use const rosasurfer\rt\BARMODEL_BAROPEN;
use const rosasurfer\rt\BARMODEL_CONTROLPOINTS;
use const rosasurfer\rt\BARMODEL_EVERYTICK;
use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_M5;
use const rosasurfer\rt\PERIOD_M15;
use const rosasurfer\rt\PERIOD_M30;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_H4;
use const rosasurfer\rt\PERIOD_D1;
use const rosasurfer\rt\PERIOD_W1;
use const rosasurfer\rt\PERIOD_MN1;
use const rosasurfer\rt\PERIOD_Q1;
use const rosasurfer\rt\TRADE_DIRECTIONS_BOTH;
use const rosasurfer\rt\TRADE_DIRECTIONS_LONG;
use const rosasurfer\rt\TRADE_DIRECTIONS_SHORT;


/**
 * MetaTrader related functionality
 */
class MT4 extends StaticClass {

    /**
     * Struct-Size des FXT-Headers (Tester-Tickdateien "*.fxt")
     */
    const FXT_HEADER_SIZE = 728;

    /**
     * Struct-Size einer History-Bar Version 400 (History-Dateien "*.hst")
     */
    const HISTORY_BAR_400_SIZE = 44;

    /**
     * Struct-Size einer History-Bar Version 401 (History-Dateien "*.hst")
     */
    const HISTORY_BAR_401_SIZE = 60;

    /**
     * Struct-Size eines Symbols (Symboldatei "symbols.raw")
     */
    const SYMBOL_SIZE = 1936;

    /**
     * Struct-Size eine Symbolgruppe (Symbolgruppendatei "symgroups.raw")
     */
    const SYMBOL_GROUP_SIZE = 80;

    /**
     * Struct-Size eines SelectedSymbol (Symboldatei "symbols.sel")
     */
    const SYMBOL_SELECTED_SIZE = 128;

    /**
     * Hoechstlaenge eines MetaTrader-Symbols
     */
    const MAX_SYMBOL_LENGTH = 11;

    /**
     * Hoechstlaenge eines MetaTrader-Orderkommentars
     */
    const MAX_ORDER_COMMENT_LENGTH = 27;


    /**
     * MetaTrader Standard-Timeframes
     */
    public static $timeframes = [PERIOD_M1, PERIOD_M5, PERIOD_M15, PERIOD_M30, PERIOD_H1, PERIOD_H4, PERIOD_D1, PERIOD_W1, PERIOD_MN1];


    /**
     * History-Bar v400
     *
     * @see  Definition in MT4Expander.dll::Expander.h
     */
    private static $tpl_HistoryBar400 = [
        'time'  => 0,
        'open'  => 0,
        'high'  => 0,
        'low'   => 0,
        'close' => 0,
        'ticks' => 0
    ];

    /**
     * History-Bar v401
     *
     * @see  Definition in MT4Expander.dll::Expander.h
     */
    private static $tpl_HistoryBar401 = [
        'time'   => 0,
        'open'   => 0,
        'high'   => 0,
        'low'    => 0,
        'close'  => 0,
        'ticks'  => 0,
        'spread' => 0,
        'volume' => 0
    ];

    /**
     * Formatbeschreibung eines struct SYMBOL.
     *
     * @see  Definition in MT4Expander.dll::Expander.h
     * @see  MT4::SYMBOL_getUnpackFormat() zum Verwenden als unpack()-Formatstring
     */
    private static $SYMBOL_formatStr = '
        /a12   name                      // szchar
        /a54   description               // szchar
        /a10   origin                    // szchar (custom)
        /a12   altName                   // szchar
        /a12   baseCurrency              // szchar
        /V     group                     // uint
        /V     digits                    // uint
        /V     tradeMode                 // uint
        /V     backgroundColor           // uint
        /V     arrayKey                  // uint
        /V     id                        // uint
        /x32   unknown1:char32
        /x208  mon:char208
        /x208  tue:char208
        /x208  wed:char208
        /x208  thu:char208
        /x208  fri:char208
        /x208  sat:char208
        /x208  sun:char208
        /x16   unknown2:char16
        /V     unknown3:int
        /V     unknown4:int
        /x4    _alignment1
        /d     unknown5:double
        /H24   unknown6:char12
        /V     spread                    // uint
        /H16   unknown7:char8
        /V     swapEnabled               // bool
        /V     swapType                  // uint
        /d     swapLongValue             // double
        /d     swapShortValue            // double
        /V     swapTripleRolloverDay     // uint
        /x4    _alignment2
        /d     contractSize              // double
        /x16   unknown8:char16
        /V     stopDistance              // uint
        /x8    unknown9:char8
        /x4    _alignment3
        /d     marginInit                // double
        /d     marginMaintenance         // double
        /d     marginHedged              // double
        /d     marginDivider             // double
        /d     pointSize                 // double
        /d     pointsPerUnit             // double
        /x24   unknown10:char24
        /a12   marginCurrency            // szchar
        /x104  unknown11:char104
        /V     unknown12:int
    ';


    /**
     * Formatbeschreibung eines struct HISTORY_BAR_400.
     *
     * @see  Definition in MT4Expander.dll::Expander.h
     * @see  MT4::BAR_getUnpackFormat() zum Verwenden als unpack()-Formatstring
     */
    private static $BAR_400_formatStr = '
        /V   time            // uint
        /d   open            // double
        /d   low             // double
        /d   high            // double
        /d   close           // double
        /d   ticks           // double
    ';


    /**
     * Formatbeschreibung eines struct HISTORY_BAR_401.
     *
     * @see  Definition in MT4Expander.dll::Expander.h
     * @see  MT4::BAR_getUnpackFormat() zum Verwenden als unpack()-Formatstring
     */
    private static $BAR_401_formatStr = '
        /V   time            // uint (int64)
        /x4
        /d   open            // double
        /d   high            // double
        /d   low             // double
        /d   close           // double
        /V   ticks           // uint (uint64)
        /x4
        /V   spread          // uint
        /V   volume          // uint (uint64)
        /x4
    ';


    /**
     * Gibt die Namen der Felder eines struct SYMBOL zurueck.
     *
     * @return string[] - Array mit SYMBOL-Feldern
     */
    public static function SYMBOL_getFields() {
        static $fields = null; if (!$fields) {
            $lines = explode(NL, self::$SYMBOL_formatStr);
            foreach ($lines as $i => &$line) {
                $line = strLeftTo($line, '//');                             // Kommentare entfernen
                $line = strRightFrom(trim($line), ' ', -1);                 // Format-Code entfernen
                if (!strlen($line) || strStartsWith($line, '_alignment'))   // Leerzeilen und Alignment-Felder loeschen
                    unset($lines[$i]);
            }; unset($line);
            $fields = array_values($lines);                                // Indizes neuordnen
        }
        return $fields;
    }


    /**
     * Gibt den Formatstring zum Entpacken eines struct SYMBOL zurueck.
     *
     * @return string - unpack()-Formatstring
     */
    public static function SYMBOL_getUnpackFormat() {
        static $format = null;

        if (is_null($format)) {
            $lines = explode("\n", self::$SYMBOL_formatStr);
            foreach ($lines as $i => &$line) {
                $line = strLeftTo($line, '//');                          // Kommentare entfernen
            }; unset($line);
            $format = join('', $lines);

            // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
            if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

            $format = preg_replace('/\s/', '', $format);                // remove white space
            if ($format[0] == '/') $format = strRight($format, -1);     // remove leading format separator
        }
        return $format;
    }


    /**
     * Gibt den Formatstring zum Packen eines struct HISTORY_BAR_400 oder HISTORY_BAR_401 zurueck.
     *
     * @param  int $version - Barversion: 400 oder 401
     *
     * @return string - pack()-Formatstring
     */
    public static function BAR_getPackFormat($version) {
        if (!is_int($version))              throw new IllegalTypeException('Illegal type of parameter $version: '.gettype($version));
        if ($version!=400 && $version!=401) throw new MetaTraderException('version.unsupported: Invalid parameter $version: '.$version.' (must be 400 or 401)');

        static $format_400 = null;
        static $format_401 = null;

        if (is_null(${'format_'.$version})) {
            $lines = explode("\n", self::${'BAR_'.$version.'_formatStr'});
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
            ${'format_'.$version} = $format;
        }
        return ${'format_'.$version};
    }


    /**
     * Gibt den Formatstring zum Entpacken eines struct HISTORY_BAR_400 oder HISTORY_BAR_401 zurueck.
     *
     * @param  int $version - Barversion: 400 oder 401
     *
     * @return string - unpack()-Formatstring
     */
    public static function BAR_getUnpackFormat($version) {
        if (!is_int($version))              throw new IllegalTypeException('Illegal type of parameter $version: '.gettype($version));
        if ($version!=400 && $version!=401) throw new MetaTraderException('version.unsupported: Invalid parameter $version: '.$version.' (must be 400 or 401)');

        static $format_400 = null;
        static $format_401 = null;

        if (is_null(${'format_'.$version})) {
            $lines = explode("\n", self::${'BAR_'.$version.'_formatStr'});
            foreach ($lines as $i => &$line) {
                $line = strLeftTo($line, '//');                          // Kommentare entfernen
            }; unset($line);
            $format = join('', $lines);

            // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
            if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

            $format = preg_replace('/\s/', '', $format);                // remove white space
            if ($format[0] == '/') $format = strRight($format, -1);     // remove leading format separator
            ${'format_'.$version} = $format;
        }
        return ${'format_'.$version};
    }


    /**
     * Schreibt eine einzelne Bar in die zum Handle gehoerende Datei. Die Bardaten werden vorm Schreiben validiert.
     *
     * @param  resource $hFile  - File-Handle eines History-Files, muss Schreibzugriff erlauben
     * @param  int      $digits - Digits des Symbols (fuer Normalisierung)
     * @param  int      $time   - Timestamp der Bar
     * @param  float    $open
     * @param  float    $high
     * @param  float    $low
     * @param  float    $close
     * @param  int      $ticks
     *
     * @return int - Anzahl der geschriebenen Bytes
     */
    public static function writeHistoryBar400($hFile, $digits, $time, $open, $high, $low, $close, $ticks) {
        // Bardaten normalisieren...
        $T = $time;
        $O = round($open,  $digits);
        $H = round($high,  $digits);
        $L = round($low,   $digits);
        $C = round($close, $digits);
        $V = $ticks;

        // vorm Schreiben nochmals pruefen (nicht mit min()/max(), da nicht performant)
        if ($O > $H || $O < $L || $C > $H || $C < $L || !$V)
            throw new RuntimeException('Illegal history bar of '.gmdate('D, d-M-Y H:i', $T).sprintf(': O='.($f='%.'.$digits.'F')." H=$f L=$f C=$f V=%d", $O, $H, $L, $C, $V));

        // Bardaten in Binaerstring umwandeln
        $data = pack('Vddddd', $T, $O, $L, $H, $C, $V);

        // pack() unterstuetzt keinen expliziten Little-Endian-Double, die Byte-Order der Doubles muss ggf. manuell reversed werden.
        static $isLittleEndian; !isset($isLittleEndian) && $isLittleEndian=isLittleEndian();
        if (!$isLittleEndian) {
            $T =        substr($data,  0, 4);
            $O = strrev(substr($data,  4, 8));
            $L = strrev(substr($data, 12, 8));
            $H = strrev(substr($data, 20, 8));
            $C = strrev(substr($data, 28, 8));
            $V = strrev(substr($data, 36, 8));
            $data  = $T.$O.$L.$H.$C.$V;
        }
        return fwrite($hFile, $data);
    }


    /**
     * Ob ein String ein gueltiges MetaTrader-Symbol darstellt. Insbesondere darf ein Symbol keine Leerzeichen enthalten.
     *
     * @return bool
     */
    public static function isValidSymbol($string) {
        static $pattern = '/^[a-z0-9_.#&\'~-]+$/i';
        return is_string($string) && strlen($string) && strlen($string) <= self::MAX_SYMBOL_LENGTH && preg_match($pattern, $string);
    }


    /**
     * Ob der angegebene Wert ein MetaTrader-Standard-Timeframe ist.
     *
     * @param  mixed $value
     *
     * @return bool
     */
    public static function isStdTimeframe($value) {
        if (is_int($value)) {
            switch ($value) {
                case PERIOD_M1 :
                case PERIOD_M5 :
                case PERIOD_M15:
                case PERIOD_M30:
                case PERIOD_H1 :
                case PERIOD_H4 :
                case PERIOD_D1 :
                case PERIOD_W1 :
                case PERIOD_MN1: return true;
            }
        }
        return false;
    }


    /**
     * Whether the specified value is a Strategy Tester bar model identifier.
     *
     * @param  mixed $value
     *
     * @return bool
     */
    public static function isBarModel($value) {
        if (is_int($value)) {
            switch ($value) {
                case BARMODEL_EVERYTICK    :
                case BARMODEL_CONTROLPOINTS:
                case BARMODEL_BAROPEN      : return true;
            }
        }
        return false;
    }


    /**
     * Ob der angegebene Wert die gueltige Beschreibung eines MetaTrader-Timeframes darstellt.
     *
     * @param  string $value - Beschreibung
     *
     * @return bool
     */
    public static function isTimeframeDescription($value) {
        if (is_string($value)) {
            if (strStartsWith($value, 'PERIOD_'))
                $value = strRight($value, -7);

            switch ($value) {
                case 'M1' : return true;
                case 'M5' : return true;
                case 'M15': return true;
                case 'M30': return true;
                case 'H1' : return true;
                case 'H4' : return true;
                case 'D1' : return true;
                case 'W1' : return true;
                case 'MN1': return true;
            }
        }
        return false;
    }


    /**
     * Convert a timeframe representation to a timeframe id.
     *
     * @param  mixed $value - timeframe representation
     *
     * @return int - period id or 0 if the value doesn't represent a period
     */
    public static function strToTimeframe($value) {
        if (is_string($value)) {
            if (!strIsNumeric($value)) {
                $value = strtoupper($value);
                if (strStartsWith($value, 'PERIOD_'))
                    $value = strRight($value, -7);
                switch ($value) {
                    case 'M1' : return PERIOD_M1;
                    case 'M5' : return PERIOD_M5;
                    case 'M15': return PERIOD_M15;
                    case 'M30': return PERIOD_M30;
                    case 'H1' : return PERIOD_H1;
                    case 'H4' : return PERIOD_H4;
                    case 'D1' : return PERIOD_D1;
                    case 'W1' : return PERIOD_W1;
                    case 'MN1': return PERIOD_MN1;
                    case 'Q1' : return PERIOD_Q1;
                }
                return 0;
            }
            $value = (float)$value;
        }

        if (is_int($value) || is_float($value)) {
            switch ((float)$value) {
                case PERIOD_M1 : return PERIOD_M1;
                case PERIOD_M5 : return PERIOD_M5;
                case PERIOD_M15: return PERIOD_M15;
                case PERIOD_M30: return PERIOD_M30;
                case PERIOD_H1 : return PERIOD_H1;
                case PERIOD_H4 : return PERIOD_H4;
                case PERIOD_D1 : return PERIOD_D1;
                case PERIOD_W1 : return PERIOD_W1;
                case PERIOD_MN1: return PERIOD_MN1;
                case PERIOD_Q1 : return PERIOD_Q1;
            }
            return 0;
        }
        throw new IllegalTypeException('Illegal type of parameter $value: '.gettype($value));
    }


    /**
     * Alias of MT4::strToTimeframe()
     *
     * @param  mixed $value - period representation
     *
     * @return int - period id or 0 if the value doesn't represent a period
     */
    public static function strToPeriod($value) {
        return static::strToTimeframe($value);
    }


    /**
     * Convert a bar model representation to a bar model id.
     *
     * @param  mixed $value - bar model representation
     *
     * @return int - bar model id or -1 if the value doesn't represent a bar model
     */
    public static function strToBarModel($value) {
        if (is_string($value)) {
            if (!strIsNumeric($value)) {
                $value = strtoupper($value);
                if (strStartsWith($value, 'BARMODEL_'))
                    $value = strRight($value, -10);
                switch ($value) {
                    case 'EVERYTICK'    : return BARMODEL_EVERYTICK;
                    case 'CONTROLPOINTS': return BARMODEL_CONTROLPOINTS;
                    case 'BAROPEN'      : return BARMODEL_BAROPEN;
                }
                return -1;
            }
            $value = (float)$value;
        }

        if (is_int($value) || is_float($value)) {
            switch ((float)$value) {
                case BARMODEL_EVERYTICK    : return BARMODEL_EVERYTICK;
                case BARMODEL_CONTROLPOINTS: return BARMODEL_CONTROLPOINTS;
                case BARMODEL_BAROPEN      : return BARMODEL_BAROPEN;
            }
            return -1;
        }
        throw new IllegalTypeException('Illegal type of parameter $value: '.gettype($value));
    }


    /**
     * Convert a Strategy Tester trade direction representation to a direction id.
     *
     * @param  mixed $value - trade direction representation
     *
     * @return int - direction id or -1 if the value doesn't represent a trade direction
     */
    public static function strToTradeDirection($value) {
        if (is_string($value)) {
            if (!strIsNumeric($value)) {
                $value = strtoupper($value);
                if (strStartsWith($value, 'TRADE_DIRECTIONS_'))
                    $value = strRight($value, -17);
                switch ($value) {
                    case 'LONG' : return TRADE_DIRECTIONS_LONG;
                    case 'SHORT': return TRADE_DIRECTIONS_SHORT;
                    case 'BOTH' : return TRADE_DIRECTIONS_BOTH;
                }
                return -1;
            }
            $value = (float)$value;
        }

        if (is_int($value) || is_float($value)) {
            switch ((float)$value) {
                case TRADE_DIRECTIONS_LONG:  return TRADE_DIRECTIONS_LONG;
                case TRADE_DIRECTIONS_SHORT: return TRADE_DIRECTIONS_SHORT;
                case TRADE_DIRECTIONS_BOTH:  return TRADE_DIRECTIONS_BOTH;
            }
            return -1;
        }
        throw new IllegalTypeException('Illegal type of parameter $value: '.gettype($value));
    }


    /**
     * Return a bar model description.
     *
     * @param  int $id - bar model id
     *
     * @return string|null - description or NULL if the parameter is not a valid bar model id
     */
    public static function barModelDescription($id) {
        $id = static::strToBarModel($id);
        if ($id >= 0) {
            switch ($id) {
                case BARMODEL_EVERYTICK:     return 'EveryTick';
                case BARMODEL_CONTROLPOINTS: return 'ControlPoints';
                case BARMODEL_BAROPEN:       return 'BarOpen';
            }
        }
        return null;
    }


    /**
     * Return a trade direction description.
     *
     * @param  int $id - direction id
     *
     * @return string|null - description or NULL if the parameter is not a valid trade direction id
     */
    public static function tradeDirectionDescription($id) {
        $id = static::strToTradeDirection($id);
        if ($id >= 0) {
            switch ($id) {
                case TRADE_DIRECTIONS_LONG:  return 'Long';
                case TRADE_DIRECTIONS_SHORT: return 'Short';
                case TRADE_DIRECTIONS_BOTH:  return 'Both';
            }
        }
        return null;
    }
}
