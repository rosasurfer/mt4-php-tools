<?php
namespace rosasurfer\rt\lib;

use rosasurfer\core\StaticClass;
use rosasurfer\exception\IllegalTypeException;

use function rosasurfer\rt\fxTime;


/**
 * Functions for working with FXT timestamps which have counterparts of the exact same name but expect Unix timestamps.
 */
class FXT extends StaticClass {


    /**
     * Format an FXT timestamp and return an FXT representation.
     *
     * @param  string $format            - format string as used for <tt>date($format, $timestamp)</tt>
     * @param  int    $fxTime [optional] - timestamp (default: the current time)
     *
     * @return string - formatted string
     */
    public static function fxDate($format, $fxTime = null) {
        if (!is_string($format))   throw new IllegalTypeException('Illegal type of parameter $format: '.gettype($format));
        if (func_num_args() < 2)   $fxTime = fxTime();
        else if (!is_int($fxTime)) throw new IllegalTypeException('Illegal type of parameter $fxTime: '.gettype($fxTime));

        return gmdate($format, $fxTime);
    }
}
