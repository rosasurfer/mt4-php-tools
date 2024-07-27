#!/usr/bin/env php
<?php
/**
 * TODO: replace by Ministruts console command
 *
 *
 * Import test results into the database.
 */
namespace rosasurfer\rt\cmd\mt4_import_test;

use rosasurfer\rt\model\Test;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\numf;
use function rosasurfer\ministruts\strIsQuoted;
use function rosasurfer\ministruts\strLeft;
use function rosasurfer\ministruts\strRight;

use const rosasurfer\ministruts\DAYS;
use const rosasurfer\ministruts\HOURS;
use const rosasurfer\ministruts\MINUTES;
use const rosasurfer\ministruts\NL;

require(dirname(realpath(__FILE__)).'/../../../app/init.php');
date_default_timezone_set('GMT');


// --- Configuration --------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// --- Start ----------------------------------------------------------------------------------------------------------------


// (1) read and validate command line arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h')   exit(1|help());                                             // help
    if ($arg == '-v') { $verbose = max($verbose, 1); unset($args[$i]); continue; }  // verbose output
}
!sizeof($args) && exit(1|help());

// (1.1) remaining arguments must be files
$files = [];
foreach ($args as $arg) {
    $value = $arg;
    strIsQuoted($value) && ($value=strLeft(strRight($value, -1), -1));      // remove surrounding quotes

    if (file_exists($value)) {                                              // explicite or shell expanded argument
        if (!is_file($value))
            continue;
        $file = pathinfo(realpath($value));
        if ($file['extension']=='ini' || $file['extension']=='log') {
            $files[$file['filename']] = $file['dirname'];
        }
        else echof('skipping non-test file "'.$value.'"');
    }
    else {                                                                  // not an existing file, try to expand wildcards
        $entries = glob($value, GLOB_NOESCAPE|GLOB_BRACE|GLOB_ERR);
        foreach ($entries as $entry) {
            if (is_file($entry)) {
                $file = pathinfo(realpath($entries));
                if ($file['extension']=='ini' || $file['extension']=='log') {
                    $files[$file['filename']] = $file['dirname'];
                }
                else echof('skipping non-test file "'.$entries.'"');
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
        echof('missing file "'.$file.'" (skipping test)');
        unset($files[$name]);
    }
}; unset($path);
!$files && exit(1|echof('error: no test result files found'));


// (2) process the files
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
    foreach ($files as $file) {
        $testConfigFile  = $file.'.ini';
        $testResultsFile = $file.'.log';

        Test::db()->begin();
        $test = Test::create($testConfigFile, $testResultsFile);

        $symbol = $test->getReportingSymbol();

        if (Test::dao()->findByReportingSymbol($symbol)) {
            Test::db()->rollback();
            echof('error: a test for reporting symbol '.$symbol.' already exists');
            return false;
        }
        $test->save();
        Test::db()->commit();

        // confirm saving
        echof('Test(id='.$test->getId().') of "'.$test->getStrategy().'" with '.$test->countTrades().' trades saved.');

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

        echof('trades:    '.$tradesPerDay.'/day');
        echof('durations: min='.$sMinDuration.'  avg='.$sAvgDuration.'  max='.$sMaxDuration);
        echof('pips:      '.$pips.'  min='.$minPips.'  avg='.$avgPips.'  max='.$maxPips);
        echof('profit:    '.numf($profit, 2).'  commission='.numf($commission, 2).'  swap='.numf($swap, 2));
        echof(NL);
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
    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self  [OPTIONS] FILE[PATTERN]...

  Options:  -v  Verbose output.
            -h  This help screen.

  FILE - One or more test result file(s) as created by the MT4Expander (may contain wildcards).


HELP;
}
