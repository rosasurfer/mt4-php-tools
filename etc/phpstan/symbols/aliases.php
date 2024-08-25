<?php
declare(strict_types=1);

/**
 * Add this file to the library path to get IDE support for PHPStan type aliases.
 * Use in PHPStan meta tags only, PHP must not see them.
 */
namespace rosasurfer\rt {

    /**
     * PHPStan alias for an array holding a single bar quoted in points.
     *
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
     * PHPStan alias for an array holding a single bar quoted in real prices.
     *
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
