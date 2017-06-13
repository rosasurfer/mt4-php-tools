#!/usr/bin/env php
<?php
namespace rosasurfer\xtrade\metatrader\import_test;

/**
 * Import test results into the database.
 */
use rosasurfer\xtrade\model\metatrader\Test;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
ini_set('memory_limit', '64M');
date_default_timezone_set('GMT');


// --- Configuration --------------------------------------------------------------------------------------------------------


$verbose         = 0;                                                // output verbosity
$testConfigFile  = null;                                             // test configuration file
$testResultsFile = null;                                             // test results file


// --- Start ----------------------------------------------------------------------------------------------------------------


// (1) read and validate command line arguments
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h')   exit(1|help());                                              // help
    if ($arg == '-v') { $verbose = max($verbose, 1); unset($args[$i]); continue; }   // verbose output
}
!sizeOf($args) && exit(1|help());

// (1.1) remaining arguments must be files
$files = [];
foreach ($args as $arg) {
    $value = $arg;
    strIsQuoted($value) && ($value=strLeft(strRight($value, -1), -1));      // remove surrounding quotes

    if (file_exists($value)) {                                              // explicite or shell expanded argument
        if (!is_file($value))
            continue;
        $file = pathInfo(realPath($value));
        if ($file['extension']=='ini' || $file['extension']=='log') {
            $files[$file['filename']] = $file['dirname'];
        }
        else echoPre('skipping non-test file "'.$value.'"');
    }
    else {                                                                  // not an existing file, try to expand wildcards
        $entries = glob($value, GLOB_NOESCAPE|GLOB_BRACE|GLOB_ERR);
        foreach ($entries as $entry) {
            if (is_file($entry)) {
                $file = pathInfo(realPath($entries));
                if ($file['extension']=='ini' || $file['extension']=='log') {
                    $files[$file['filename']] = $file['dirname'];
                }
                else echoPre('skipping non-test file "'.$entries.'"');
            }
        }
    }
}

// (1.1) check existence of both a test's .ini and .log file
foreach ($files as $name => &$path) {
    if (is_file($file=$path.DIRECTORY_SEPARATOR.$name.'.ini') && is_file($file=$path.DIRECTORY_SEPARATOR.$name.'.log')) {
        $path .= DIRECTORY_SEPARATOR.$name;
    }
    else {
        echoPre('missing file "'.$file.'" (skipping test)');
        unset($files[$name]);
    }
}; unset($path);
!$files && exit(1|echoPre('error: no test result files found'));


// (2) install SIGINT handler (catches Ctrl-C)                          // To execute destructors it's sufficient to call
if (!WINDOWS) pcntl_signal(SIGINT, function($signo) { exit(); });       // exit() in the handler.


// (3) process the files
processTestFiles($files) || exit(1);

exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Process the test files.
 *
 * @param string[] $files
 *
 * @return bool - success status
 */
function processTestFiles(array $files) {
    global $verbose;

    foreach ($files as $file) {
        $testConfigFile  = $file.'.ini';
        $testResultsFile = $file.'.log';

        Test::db()->begin();
        $test = Test::create($testConfigFile, $testResultsFile);

        $symbol = $test->getReportingSymbol();

        if (Test::dao()->findByReportingSymbol($symbol)) {
            Test::db()->rollback();
            echoPre('error: a test for reporting symbol '.$symbol.' already exists');
            return false;
        }
        $test->save();
        Test::db()->commit();

        // confirm saving
        echoPre('Test(id='.$test->getId().') of "'.$test->getStrategy().'" with '.$test->countTrades().' trades saved.');

        // print statistics
        $stats        = $test->getStats();
        $tradesPerDay = $stats->getTradesPerDay();

        $sec          = $stats->getMinDuration();
        $sMinDuration = sprintf('%02d:%02d:%02d', $sec/DAYS, $sec%HOURS/MINUTES, $sec%MINUTES);
        $sec          = $stats->getAvgDuration();
        $sAvgDuration = sprintf('%02d:%02d:%02d', $sec/DAYS, $sec%HOURS/MINUTES, $sec%MINUTES);
        $sec          = $stats->getMaxDuration();
        $sMaxDuration = sprintf('%02d:%02d:%02d', $sec/DAYS, $sec%HOURS/MINUTES, $sec%MINUTES);

        $pips         = $stats->getPips();
        $minPips      = $stats->getMinPips();
        $avgPips      = $stats->getAvgPips();
        $maxPips      = $stats->getMaxPips();

        $profit       = $stats->getGrossProfit();
        $commission   = $stats->getCommission();
        $swap         = $stats->getSwap();

        echoPre('trades:    '.$tradesPerDay.'/day');
        echoPre('durations: min='.$sMinDuration.'  avg='.$sAvgDuration.'  max='.$sMaxDuration);
        echoPre('pips:      '.$pips.'  min='.$minPips.'  avg='.$avgPips.'  max='.$maxPips);
        echoPre('profit:    '.numf($profit, 2).'  commission='.numf($commission, 2).'  swap='.numf($swap, 2));
        echoPre(NL);
    }
    return true;
}


/**
 * Show help screen.
 *
 * @param  string $message [optional] - additional message to display (default: none)
 */
function help($message = null) {
    if (is_null($message))
        $message = 'Import test logs into the database.';
    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self  [OPTIONS] FILE[PATTERN]...

  Options:  -v  Verbose output.
            -h  This help screen.

  FILE - One or more test result file(s) as created by MT4Expander (may contain wildcards).


HELP;
}
