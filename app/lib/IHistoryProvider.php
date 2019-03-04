<?php
namespace rosasurfer\rt\lib;


/**
 * IHistoryProvider
 *
 * An interface for classes capable of returning historic prices for different timeframes.
 */
interface IHistoryProvider {


    /**
     * Return historic price bars for the specified timeframe and time.
     *
     * @param  int $timeframe - timeframe identifier: M1, M5, M15 etc.
     * @param  int $time      - FXT timestamp of the time to return prices for. If 0 (zero) the oldest available prices for
     *                          the specified timeframe are returned.
     *
     * @return array[] - If history for the specified time and timeframe is not available an empty array is returned.
     *                   Otherwise a timeseries array is returned with each element describing a single price bar as follows:
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time (FXT)
     *     'open'  => (float),          // open value
     *     'high'  => (float),          // high value
     *     'low'   => (float),          // low value
     *     'close' => (float),          // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ];
     * </pre>
     */
    public function getHistory($timeframe, $time);
}
