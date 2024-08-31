<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib;

use rosasurfer\ministruts\core\StaticClass;
use rosasurfer\rt\view\ViewHelper;

use const rosasurfer\rt\OP_CREDIT;


/**
 * Validator
 */
class Validator extends StaticClass {


    /**
     * Ob der uebergebene Wert ein gueltiger OperationType-Identifier ist.
     *
     * @param  int $type - zu pruefender Wert
     *
     * @return bool
     */
    public static function isOperationType($type) {
        return (is_int($type) && isset(ViewHelper::$operationTypes[$type]));
    }


    /**
     * Ob der uebergebene Wert ein gueltiger MT4-OperationType-Identifier ist.
     *
     * @param  int $type - zu pruefender Wert
     *
     * @return bool
     */
    public static function isMT4OperationType($type) {
        return (self::isOperationType($type) && $type <= OP_CREDIT);
    }


    /**
     * Ob der uebergebene Wert ein gueltiger Custom-OperationType-Identifier ist.
     *
     * @param  int $type - zu pruefender Wert
     *
     * @return bool
     */
    public static function isCustomOperationType($type) {
        return (self::isOperationType($type) && $type > OP_CREDIT);
    }


    /**
     * Ob der uebergebene String ein gueltiger Instrumentbezeichner ist.
     *
     * @param  string $string - zu pruefender Sring
     *
     * @return bool
     */
    public static function isInstrument($string) {
        return (is_string($string) && isset(ViewHelper::$instruments[$string]));
    }
}
