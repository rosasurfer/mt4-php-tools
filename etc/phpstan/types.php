<?php
declare(strict_types=1);

/**
 * Pseudo-type definitions for the IDE. Add to the build path to prevent validation errors about custom PHPStan types.
 *
 *
 * @see  https://phpstan.org/writing-php-code/phpdoc-types#global-type-aliases
 * @see  https://phpstan.org/writing-php-code/phpdoc-types#local-type-aliases
 *
 * @phpstan-type  POINT_BAR  array{time:int, open:int,   high:int,   low:int,   close:int,   ticks:int}
 * @phpstan-type  PRICE_BAR  array{time:int, open:float, high:float, low:float, close:float, ticks:int}
 */
namespace rosasurfer\rt {

    /**
     * <pre>
     * array(
     *   'time'  => (int),      // bar open time in FXT
     *   'open'  => (int),      // open price in point
     *   'high'  => (int),      // high price in point
     *   'low'   => (int),      // low price in point
     *   'close' => (int),      // close price in point
     *   'ticks' => (int),      // volume (if available) or number of ticks
     * )
     * </pre>
     */
    class POINT_BAR {}

    /**
     * <pre>
     * array(
     *   'time'  => (int),      // bar open time in FXT
     *   'open'  => (float),    // open price
     *   'high'  => (float),    // high price
     *   'low'   => (float),    // low price
     *   'close' => (float),    // close price
     *   'ticks' => (int),      // volume (if available) or number of ticks
     * )
     * </pre>
     */
    class PRICE_BAR {}
}
