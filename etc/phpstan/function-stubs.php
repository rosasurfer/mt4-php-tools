<?php declare(strict_types=1);

namespace {

    if (!function_exists('pcntl_signal')) {
        /**
         * @param  int          $signo
         * @param  callable|int $handler
         * @param  bool         $restart_syscalls
         * @return bool
         */
        function pcntl_signal($signo, $handler, $restart_syscalls = null) {}

        /**
         * @return bool
         */
        function pcntl_signal_dispatch() {}
    }
}


namespace rosasurfer\trade\dukascopy\update_m1_bars {

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function checkHistory($symbol, $day) {}

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
     * @param  string $id
     * @param  string $symbol
     * @param  int    $time
     * @param  string $type
     * @return string
     */
    function getVar($id, $symbol=null, $time=null, $type=null) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}

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
     * @return bool
     */
    function processCompressedDukascopyBarData($data, $symbol, $day, $type) {}

    /**
     * @return bool
     */
    function processCompressedDukascopyBarFile($file, $symbol, $day, $type) {}

    /**
     * @return bool
     */
    function processRawDukascopyBarData($data, $symbol, $day, $type) {}

    /**
     * @return bool
     */
    function processRawDukascopyBarFile($file, $symbol, $day, $type) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function saveBars($symbol, $day) {}

    /**
     *
     */
    function showBuffer() {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @return bool
     */
    function updateHistory($symbol, $day) {}

    /**
     * @param  string $symbol
     * @return bool
     */
    function updateSymbol($symbol) {}
}


namespace rosasurfer\trade\dukascopy\update_tickdata {

    /**
     * @param  string $symbol
     * @return bool
     */
    function updateSymbol($symbol) {}

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @return bool
     */
    function checkHistory($symbol, $gmtHour, $fxtHour) {}

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @return bool
     */
    function updateTicks($symbol, $gmtHour, $fxtHour) {}

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @return array
     */
    function loadTicks($symbol, $gmtHour, $fxtHour) {}

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @param  array  $ticks
     * @return bool
     */
    function saveTicks($symbol, $gmtHour, $fxtHour, array $ticks) {}

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @param  bool   $quiet
     * @param  bool   $saveData
     * @param  bool   $saveError
     * @return string
     */
    function downloadTickdata($symbol, $gmtHour, $fxtHour, $quiet=false, $saveData=false, $saveError=true) {}

    /**
     * @return array
     */
    function loadCompressedDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {}

    /**
     * @return array
     */
    function loadCompressedDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {}

    /**
     * @return array
     */
    function loadRawDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {}

    /**
     * @return array
     */
    function loadRawDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {}

    /**
     * @param  string $id
     * @param  string $symbol
     * @param  int    $time
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\fxi\update_m1_bars {

    /**
     * @param  string $index
     * @return bool
     */
    function updateIndex($index) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateAUDFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateAUDFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateAUDLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateCADFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateCADFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateCADLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateCHFFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateCHFFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateCHFLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateEURFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateEURFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateEURLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateEURX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateGBPFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateGBPFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateGBPLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateJPYFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateJPYFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateJPYLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateNOKFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateNZDFX7($day, array $data) {}

    /**
     * @param  int    $day
     * @param  array  $data
     * @param  string $name
     * @return array
     */
    function calculateNZDLFX($day, array $data, $name='NZDLFX') {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateSEKFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateSGDFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateUSDFX6($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateUSDFX7($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateUSDLFX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateUSDX($day, array $data) {}

    /**
     * @param  int   $day
     * @param  array $data
     * @return array
     */
    function calculateZARFX7($day, array $data) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  array  $bars
     * @return bool
     */
    function saveBars($symbol, $day, array $bars) {}

    /**
     * @param  array $bars
     */
    function showBuffer($bars) {}

    /**
     * @param  string $id
     * @param  string $symbol
     * @param  int    $time
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\logwatch {

    /**
     * @param  string $message
     */
    function error($message) {}

    /**
     * @param  string $message
     */
    function help($message = null) {}

    /**
     * @param  string $entry
     */
    function processEntry($entry) {}
}


namespace rosasurfer\trade\metatrader\create_history {

    /**
     * @param  string $symbol
     * @return bool
     */
    function createHistory($symbol) {}

    /**
     * @param  string $id
     * @param  string $symbol
     * @param  int    $time
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\metatrader\create_tickfile {

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\metatrader\dir {

    /**
     * @param  string $dirName
     * @param  array  ...
     */
    function showDirResults($dirName, array $files, array $formats, array $symbols, array $symbolsU, array $periods, array $digits, array $syncMarkers, array $lastSyncTimes, array $bars, array $barsFrom, array $barsTo, array $errors) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\metatrader\find_offset {

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\trade\metatrader\list_symbols {

    /**
     * @param  string $file
     * @param  array &$fields
     * @param  array &$data
     * @param  array  $options
     * @return bool
     */
    function collectData($file, array &$fields, array &$data, array $options) {}

    /**
     * @param  string $fileA
     * @param  string $fileB
     * @return int
     */
    function compareFileNames($fileA, $fileB) {}

    /**
     * @param  string $message
     */
    function help($message=null) {}

    /**
     * @param  string[] $files
     * @param  array    $fields
     * @param  array    $data
     * @param  array    $options
     * @return bool
     */
    function printData(array $files, array $fields, array $data, array $options) {}
}


namespace rosasurfer\trade\metatrader\save_test {

    /**
     * @param  string $message
     */
    function help($message = null) {}

    /**
     * @return bool
     */
    function processTestFiles() {}
}


namespace rosasurfer\trade\metatrader\update_history {

    /**
     * @param  string $message
     */
    function help($message=null) {}

    /**
     * @param  string $symbol
     * @return bool
     */
    function updateHistory($symbol) {}
}


namespace rosasurfer\trade\myfxbook\sync_accounts {

    use rosasurfer\trade\model\Signal;

    /**
     * @param  string $message
     */
    function help($message=null) {}

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
