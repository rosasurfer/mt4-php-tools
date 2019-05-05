<?php
namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\core\Object;


/**
 * A PHP representation of a MetaTrader symbol definition. The file "symbols.raw" is a concatenation of C++ structs of such
 * symbols. For the C++ definition see the following link.
 *
 * @see  https://github.com/rosasurfer/mt4-expander/blob/master/header/struct/mt4/Symbol.h
 */
class Symbol extends Object {


    /** @var int - struct size of a symbol in bytes */
    const SIZE = 1936;

    /** @var string - unpack format description of a struct <tt>SYMBOL</tt> */
    const UNPACK_DEFINITION = '
        /Z12   name                      // szchar
        /Z54   description               // szchar
        /Z10   origin                    // szchar (custom field)
        /Z12   altName                   // szchar
        /Z12   baseCurrency              // szchar
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
        /Z12   marginCurrency            // szchar
        /x104  unknown11:char104
        /V     unknown12:int
    ';


    /**
     * Return the format string for parsing a struct <tt>SYMBOL</tt> with the PHP function <tt>unpack()</tt>.
     *
     * @return string
     */
    public static function unpackFormat() {
        static $format = null;
        if (!$format) {
            $lines = explode(EOL_UNIX, normalizeEOL(self::UNPACK_DEFINITION, EOL_UNIX));
            foreach ($lines as $i => &$line) {
                $line = strLeftTo($line, '//');                         // drop line comments
            }; unset($line);
            $format = join('', $lines);
            $format = str_replace('/a', '/Z', $format);                 // since PHP 5.5 'Z' replaces the former 'a'
            $format = preg_replace('/\s/', '', $format);                // remove white space
        }
        return $format;
    }
}
