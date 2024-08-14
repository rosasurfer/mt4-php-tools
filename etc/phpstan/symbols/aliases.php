<?php
declare(strict_types=1);

/**
 * IDE tooltip support for PHPStan type aliases. The types are used in PHPDoc only, PHP never sees them.
 * Add this file to the library path of the project.
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

namespace rosasurfer\rt\lib\synthetic {
    /** @see \rosasurfer\rt\PRICE_BAR */
    class PRICE_BAR extends \rosasurfer\rt\PRICE_BAR {}
}

namespace rosasurfer\rt\lib\synthetic\index {
    /** @see \rosasurfer\rt\PRICE_BAR */
    class PRICE_BAR extends \rosasurfer\rt\PRICE_BAR {}
}

namespace rosasurfer\rt\model {
    /** @see \rosasurfer\rt\POINT_BAR */
    class POINT_BAR extends \rosasurfer\rt\POINT_BAR {}

    /** @see \rosasurfer\rt\PRICE_BAR */
    class PRICE_BAR extends \rosasurfer\rt\PRICE_BAR {}
}
