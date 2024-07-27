<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib;


/**
 * IHistorySource
 *
 * An interface for classes capable of providing the original history for a Rosatrader symbol.
 */
interface IHistorySource {


    /**
     * Get history for the specified bar period and time.
     *
     * @param  int  $period               - bar period identifier: PERIOD_M1 | PERIOD_M5 | PERIOD_M15 etc.
     * @param  int  $time                 - FXT time to return prices for. If 0 (zero) the oldest available prices for the
     *                                      requested bar period are returned.
     * @param  bool $optimized [optional] - returned bar format (see notes)
     *
     * @return array - An empty array if history for the specified bar period and time is not available. Otherwise a
     *                 timeseries array with each element describing a single price bar as follows:
     *
     * <pre>
     * $optimized => FALSE (default):
     * ------------------------------
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (float),          // open value in real terms
     *     'high'  => (float),          // high value in real terms
     *     'low'   => (float),          // low value in real terms
     *     'close' => (float),          // close value in real terms
     *     'ticks' => (int),            // volume (if available) or number of synthetic ticks
     * )
     *
     * $optimized => TRUE:
     * -------------------
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (int),            // open value in point
     *     'high'  => (int),            // high value in point
     *     'low'   => (int),            // low value in point
     *     'close' => (int),            // close value in point
     *     'ticks' => (int),            // volume (if available) or number of synthetic ticks
     * )
     * </pre>
     */
    public function getHistory($period, $time, $optimized = false);
}
