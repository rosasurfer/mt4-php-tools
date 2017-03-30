<?php
namespace rosasurfer\trade\dukascopy\update_m1_bars {

    /**
     * @param  string $symbol
     * @return bool
     */
    function updateSymbol($symbol) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function checkHistory($symbol, $day) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function updateHistory($symbol, $day) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  string $type
     * @return bool
     */
    function loadHistory($symbol, $day, $type) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function mergeHistory($symbol, $day) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  string $type
     * @param  bool   $quiet
     * @param  bool   $saveData
     * @param  bool   $saveError
     * @return string
     */
    function downloadData($symbol, $day, $type, $quiet=false, $saveData=false, $saveError=true) {}

    /**
     * @return bool
     */
    function processCompressedDukascopyBarFile($file, $symbol, $day, $type) {}

    /**
     * @return bool
     */
    function processCompressedDukascopyBarData($data, $symbol, $day, $type) {}

    /**
     * @return bool
     */
    function processRawDukascopyBarFile($file, $symbol, $day, $type) {}

    /**
     * @return bool
     */
    function processRawDukascopyBarData($data, $symbol, $day, $type) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function saveBars($symbol, $day) {}

    /**
     * @param  string $id
     * @param  string $symbol
     * @param  int    $time
     * @param  string $type
     * @return string
     */
    function getVar($id, $symbol=null, $time=null, $type=null) {}

    /**
     *
     */
    function showBuffer() {}

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\logwatch {

    /**
     * @param  string $entry
     */
    function processEntry($entry) {}

    /**
     * @param  string $message
     */
    function error($message) {}

    /**
     * @param  string $message
     */
    function help($message = null) {}
}


namespace rosasurfer\trade\myfxbook\sync_accounts {

    use rosasurfer\trade\model\Signal;

    /**
     * @param  string $alias
     * @return bool
     */
    function processAccounts($alias) {}

    /**
     * @param  Signal $signal
     * @param  array  $currentOpenPositions
     * @param  bool   $openUpdates
     * @param  array  $currentHistory
     * @param  bool   $closedUpdates
     * @param  bool   $isFullHistory
     * @return bool
     */
    function updateDatabase(Signal $signal, array $currentOpenPositions, &$openUpdates, array $currentHistory, &$closedUpdates, $isFullHistory) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\simpletrader\sync_accounts {

    use rosasurfer\trade\model\Signal;

    /**
     * @param  string $message
     */
    function help($message=null) {}

    /**
     * @param  string $alias
     * @param  bool   $fileSyncOnly
     * @return bool
     */
    function processSignal($alias, $fileSyncOnly) {}

    /**
     * @param  Signal $signal
     * @param  array  $currentOpenPositions
     * @param  bool  &$openUpdates
     * @param  array  $currentHistory
     * @param  bool  &$closedUpdates
     * @param  bool   $fullHistory
     * @return bool
     */
    function updateDatabase(Signal $signal, array &$currentOpenPositions, &$openUpdates, array &$currentHistory, &$closedUpdates, $fullHistory) {}
}
