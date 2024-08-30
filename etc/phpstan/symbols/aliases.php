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
     * POINT_BAR = array(
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
     * PRICE_BAR = array(
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

    /**
     * PHPStan alias for a logfile entry holding the logged properties of an order.
     *
     * <pre>
     * LOG_ORDER = array(
     *   'id'          => (int),        //
     *   'ticket'      => (int),        //
     *   'type'        => (int),        //
     *   'lots'        => (float),      //
     *   'symbol'      => (string),     //
     *   'openPrice'   => (float),      //
     *   'openTime'    => (int),        //
     *   'stopLoss'    => (float),      //
     *   'takeProfit'  => (float),      //
     *   'closePrice'  => (float),      //
     *   'closeTime'   => (int),        //
     *   'commission'  => (float),      //
     *   'swap'        => (float),      //
     *   'profit'      => (float),      //
     *   'magicNumber' => (int),        //
     *   'comment'     => (string),     //
     * )
     * </pre>
     */
    class LOG_ORDER {}

    /**
     * PHPStan alias for a logfile entry holding the logged properties of a test.
     *
     * <pre>
     * LOG_TEST = array(
     *   'id'              => (int),
     *   'time'            => (int),                // creation time (local TZ)
     *   'strategy'        => (non-empty-string),
     *   'reportingId'     => (int),
     *   'reportingSymbol' => (non-empty-string),
     *   'symbol'          => (non-empty-string),
     *   'timeframe'       => (int),
     *   'startTime'       => (int),                // history start time (FXT)
     *   'endTime'         => (int),                // history end time (FXT)
     *   'barModel'        => 0|1|2,
     *   'spread'          => (float),
     *   'bars'            => (int),
     *   'ticks'           => (int),
     * )
     * </pre>
     */
    class LOG_TEST {}
}
