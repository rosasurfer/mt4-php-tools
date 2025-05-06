<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib;


/**
 * HistorySource
 *
 * An interface for classes which can provide the original history for an application symbol.
 *
 * @phpstan-import-type RT_POINT_BAR from \rosasurfer\rt\phpstan\CustomTypes
 * @phpstan-import-type RT_PRICE_BAR from \rosasurfer\rt\phpstan\CustomTypes
 */
interface HistorySource {

    /**
     * Get the history for the specified bar period and time.
     *
     * @param  int  $period             - bar period identifier: PERIOD_M1 | PERIOD_M5 | PERIOD_M15 etc.
     * @param  int  $time               - FXT time to return prices for. If 0 (zero) the oldest available prices for the
     *                                    requested bar period are returned.
     * @param  bool $compact [optional] - returned bar format (default: more compact RT_POINT_BARs)
     *
     * @return array[] - bar array or an empty array if history for the specified parameters is not available
     *
     * @phpstan-return ($compact is true ? RT_POINT_BAR[] : RT_PRICE_BAR[])
     *
     * @see \rosasurfer\rt\phpstan\RT_POINT_BAR
     * @see \rosasurfer\rt\phpstan\RT_PRICE_BAR
     */
    public function getHistory(int $period, int $time, bool $compact = true): array;
}
