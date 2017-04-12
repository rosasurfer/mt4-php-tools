<?php declare(strict_types=1);

namespace rosasurfer\xtrade\dukascopy\update_m1_bars {

    /**
     * @param  string $symbol
     * @param  int    $day
     *
     * @return bool
     */
    function checkHistory($symbol, $day) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  string $type
     * @param  bool   $quiet
     * @param  bool   $saveData
     * @param  bool   $saveError
     *
     * @return string
     */
    function downloadData($symbol, $day, $type, $quiet=false, $saveData=false, $saveError=true) {
        return '';
    }

    /**
     * @param  string      $id
     * @param  string|null $symbol
     * @param  int|null    $time
     * @param  string|null $type
     *
     * @return string
     */
    function getVar($id, $symbol=null, $time=null, $type=null) {
        return '';
    }

    /**
     * @param  string|null $message
     */
    function help($message=null) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  string $type
     *
     * @return bool
     */
    function loadHistory($symbol, $day, $type) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $day
     *
     * @return bool
     */
    function mergeHistory($symbol, $day) {
        return false;
    }

    /**
     * @return bool
     */
    function processCompressedDukascopyBarData($data, $symbol, $day, $type) {
        return false;
    }

    /**
     * @return bool
     */
    function processCompressedDukascopyBarFile($file, $symbol, $day, $type) {
        return false;
    }

    /**
     * @return bool
     */
    function processRawDukascopyBarData($data, $symbol, $day, $type) {
        return false;
    }

    /**
     * @return bool
     */
    function processRawDukascopyBarFile($file, $symbol, $day, $type) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $day
     *
     * @return bool
     */
    function saveBars($symbol, $day) {
        return false;
    }

    /**
     *
     */
    function showBarBuffer() {}

    /**
     * @param  string $symbol
     * @param  int    $day
     *
     * @return bool
     */
    function updateHistory($symbol, $day) {
        return false;
    }

    /**
     * @param  string $symbol
     *
     * @return bool
     */
    function updateSymbol($symbol) {
        return false;
    }
}


namespace rosasurfer\xtrade\dukascopy\update_tickdata {

    /**
     * @param  string $symbol
     *
     * @return bool
     */
    function updateSymbol($symbol) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     *
     * @return bool
     */
    function checkHistory($symbol, $gmtHour, $fxtHour) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     *
     * @return bool
     */
    function updateTicks($symbol, $gmtHour, $fxtHour) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     *
     * @return array[]|bool
     */
    function loadTicks($symbol, $gmtHour, $fxtHour) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @param  array  $ticks
     *
     * @return bool
     */
    function saveTicks($symbol, $gmtHour, $fxtHour, array $ticks) {
        return false;
    }

    /**
     * @param  string $symbol
     * @param  int    $gmtHour
     * @param  int    $fxtHour
     * @param  bool   $quiet
     * @param  bool   $saveData
     * @param  bool   $saveError
     *
     * @return string
     */
    function downloadTickdata($symbol, $gmtHour, $fxtHour, $quiet=false, $saveData=false, $saveError=true) {
        return '';
    }

    /**
     * @return array[]
     */
    function loadCompressedDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {
        return [[]];
    }

    /**
     * @return array[]
     */
    function loadCompressedDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {
        return [[]];
    }

    /**
     * @return array[]
     */
    function loadRawDukascopyTickFile($file, $symbol, $gmtHour, $fxtHour) {
        return [[]];
    }

    /**
     * @return array[]
     */
    function loadRawDukascopyTickData($data, $symbol, $gmtHour, $fxtHour) {
        return [[]];
    }

    /**
     * @param  string      $id
     * @param  string|null $symbol
     * @param  int|null    $time
     *
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {
        return '';
    }

    /**
     * @param  string|null $message
     */
    function help($message=null) {}
}


namespace rosasurfer\xtrade\fxi\update_m1_bars {

    /**
     * @param  string $index
     *
     * @return bool
     */
    function updateIndex($index) {
        return false;
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateAUDFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateAUDFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateAUDLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateCADFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateCADFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateCADLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateCHFFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateCHFFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateCHFLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateEURFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateEURFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateEURLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateEURX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateGBPFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateGBPFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateGBPLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateJPYFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateJPYFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateJPYLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateNOKFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateNZDFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int    $day
     * @param  array  $data
     * @param  string $name
     *
     * @return array
     */
    function calculateNZDLFX($day, array $data, $name='NZDLFX') {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateSEKFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateSGDFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateUSDFX6($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateUSDFX7($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateUSDLFX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateUSDX($day, array $data) {
        return [];
    }

    /**
     * @param  int   $day
     * @param  array $data
     *
     * @return array
     */
    function calculateZARFX7($day, array $data) {
        return [];
    }

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  array  $bars
     *
     * @return bool
     */
    function saveBars($symbol, $day, array $bars) {
        return false;
    }

    /**
     * @param  array $bars
     */
    function showBuffer($bars) {}

    /**
     * @param  string      $id
     * @param  string|null $symbol
     * @param  int|null    $time
     *
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {
        return '';
    }

    /**
     * @param  string|null $message
     */
    function help($message=null) {}
}


namespace rosasurfer\xtrade\logwatch {

    /**
     * @param  string $message
     */
    function error($message) {}

    /**
     * @param  string|null $message
     */
    function help($message = null) {}

    /**
     * @param  string $entry
     */
    function processEntry($entry) {}
}


namespace rosasurfer\xtrade\metatrader\create_history {

    /**
     * @param  string $symbol
     *
     * @return bool
     */
    function createHistory($symbol) {
        return false;
    }

    /**
     * @param  string      $id
     * @param  string|null $symbol
     * @param  int|null    $time
     *
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {
        return '';
    }

    /**
     * @param  string $message
     */
    function help($message=null) {}
}


namespace rosasurfer\xtrade\metatrader\create_tickfile {

    /**
     * @param  string|null $message
     */
    function help($message=null) {}
}


namespace rosasurfer\xtrade\metatrader\dir {

    /**
     * @param  string $dirName
     * @param  array  ...
     */
    function showDirResults($dirName, array $files, array $formats, array $symbols, array $symbolsU, array $periods, array $digits, array $syncMarkers, array $lastSyncTimes, array $bars, array $barsFrom, array $barsTo, array $errors) {}

    /**
     * @param  string|null $message
     */
    function help($message=null) {}
}


namespace rosasurfer\xtrade\metatrader\find_offset {

    /**
     * @param  string|null $message
     */
    function help($message=null) {}
}


namespace rosasurfer\xtrade\metatrader\list_symbols {

    /**
     * @param  string $file
     * @param  array &$fields
     * @param  array &$data
     * @param  array  $options
     *
     * @return bool
     */
    function collectData($file, array &$fields, array &$data, array $options) {
        return false;
    }

    /**
     * @param  string $fileA
     * @param  string $fileB
     *
     * @return int
     */
    function compareFileNames($fileA, $fileB) {
        return 0;
    }

    /**
     * @param  string|null $message
     */
    function help($message=null) {}

    /**
     * @param  string[] $files
     * @param  array    $fields
     * @param  array    $data
     * @param  array    $options
     *
     * @return bool
     */
    function printData(array $files, array $fields, array $data, array $options) {
        return false;
    }
}


namespace rosasurfer\xtrade\metatrader\save_test {

    /**
     * @param  string|null $message
     */
    function help($message = null) {}

    /**
     * @return bool
     */
    function processTestFiles() {
        return false;
    }
}


namespace rosasurfer\xtrade\metatrader\update_history {

    /**
     * @param  string|null $message
     */
    function help($message=null) {}

    /**
     * @param  string $symbol
     *
     * @return bool
     */
    function updateHistory($symbol) {
        return false;
    }
}


namespace rosasurfer\xtrade\myfxbook\sync_accounts {

    use rosasurfer\xtrade\model\Signal;

    /**
     * @param  string|null $message
     */
    function help($message=null) {}

    /**
     * @param  string $alias
     *
     * @return bool
     */
    function processAccounts($alias) {
        return false;
    }

    /**
     * @param  Signal $signal
     * @param  array  $currentOpenPositions
     * @param  bool   $openUpdates
     * @param  array  $currentHistory
     * @param  bool   $closedUpdates
     * @param  bool   $isFullHistory
     *
     * @return bool
     */
    function updateDatabase(Signal $signal, array $currentOpenPositions, &$openUpdates, array $currentHistory, &$closedUpdates, $isFullHistory) {
        return false;
    }
}


namespace rosasurfer\xtrade\simpletrader\sync_accounts {

    use rosasurfer\xtrade\model\Signal;

    /**
     * @param  string|null $message
     */
    function help($message=null) {}

    /**
     * @param  string $alias
     * @param  bool   $fileSyncOnly
     *
     * @return bool
     */
    function processSignal($alias, $fileSyncOnly) {
        return false;
    }

    /**
     * @param  Signal $signal
     * @param  array  $currentOpenPositions
     * @param  bool  &$openUpdates
     * @param  array  $currentHistory
     * @param  bool  &$closedUpdates
     * @param  bool   $fullHistory
     *
     * @return bool
     */
    function updateDatabase(Signal $signal, array &$currentOpenPositions, &$openUpdates, array &$currentHistory, &$closedUpdates, $fullHistory) {
        return false;
    }
}
