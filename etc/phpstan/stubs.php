<?php declare(strict_types=1);

namespace rosasurfer\rt\bin\logwatch {

    /**
     * @param  string $message [optional]
     */
    function help($message = null) {}

    /**
     * @param  string $entry
     */
    function processEntry($entry) {}
}


namespace rosasurfer\rt\cmd\app_generate_pl_series {

    const PIP  = 0;
    const PIPS = PIP;

    const POINT  = 0;
    const POINTS = POINT;

    /**
     * @param  string $id
     * @param  string $symbol [optional]
     * @param  int    $time   [optional]
     *
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {
        return '';
    }

    /**
     * @param  string $message [optional]
     */
    function help($message = null) {}

    /**
     * @param  string $symbol
     * @param  int    $day
     * @param  array  $bars
     * @param  bool   $partial [optional]
     *
     * @return bool
     */
    function saveBars($symbol, $day, array $bars, $partial = false) {
        return(true);
    }
}


namespace rosasurfer\rt\cmd\app_update_synthetic_bars {

    /**
     * @param  string $message [optional]
     */
    function help($message=null) {}
}


namespace rosasurfer\rt\cmd\duk_update_ticks {

    use rosasurfer\rt\model\RosaSymbol;

    /**
     * @param  RosaSymbol $symbol
     *
     * @return bool
     */
    function updateSymbol(RosaSymbol $symbol) {
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
     * @param  string $id
     * @param  string $symbol [optional]
     * @param  int    $time   [optional]
     *
     * @return string
     */
    function getVar($id, $symbol=null, $time=null) {
        return '';
    }

    /**
     * @param  string $message [optional]
     */
    function help($message=null) {}
}


namespace rosasurfer\rt\cmd\mt4_create_tick_file {

    /**
     * @param  string $message [optional]
     */
    function help($message=null) {}
}


namespace rosasurfer\rt\cmd\mt4_dir {

    /**
     * @param  string $dirName
     * @param  array  ...
     */
    function showDirResults($dirName, array $files, array $formats, array $symbols, array $symbolsU, array $periods, array $digits, array $syncMarkers, array $lastSyncTimes, array $bars, array $barsFrom, array $barsTo, array $errors) {}

    /**
     * @param  string $message [optional]
     */
    function help($message=null) {}
}


namespace rosasurfer\rt\cmd\mt4_find_offset {

    /**
     * @param  string $message [optional]
     */
    function help($message=null) {}
}


namespace rosasurfer\rt\cmd\mt4_import_test {

    /**
     * @param  string $message [optional]
     */
    function help($message = null) {}

    /**
     * @param  string[] $files
     *
     * @return bool
     */
    function processTestFiles(array $files) {
        return false;
    }
}


namespace rosasurfer\rt\cmd\mt4_list_symbols {

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
     * @param  string $message [optional]
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


namespace rosasurfer\rt\cmd\mt4_update_history {

    use rosasurfer\rt\model\RosaSymbol;

    /**
     * @param  string $message [optional]
     */
    function help($message=null) {}

    /**
     * @param  RosaSymbol $symbol
     *
     * @return bool
     */
    function updateHistory(RosaSymbol $symbol) {
        return false;
    }
}
