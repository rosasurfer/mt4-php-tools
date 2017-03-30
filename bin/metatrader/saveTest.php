#!/usr/bin/php
<?php
/**
 * Save a test and its trade history in the database.
 */
use rosasurfer\trade\model\metatrader\Test;


require(__DIR__.'/../../app/init.php');
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

// (1.1) the remaining argument must be a file
sizeOf($args)!=1 && exit(1|help());
$fileName = array_shift($args);
!is_file($fileName) && exit(1|echoPre('file not found: "'.$fileName.'"'));

// (1.2) fileName must be a test configuration or a test result file
$ext = (new SplFileInfo($fileName))->getExtension();
if ($ext == 'ini') {
    $testConfigFile  = $fileName;
    $testResultsFile = strLeft($fileName, -strLen($ext)).'log';
    !is_file($testResultsFile) && exit(1|echoPre('test results file not found: "'.$testResultsFile.'"'));
}
elseif ($ext == 'log') {
    $testConfigFile  = strLeft($fileName, -strLen($ext)).'ini';
    $testResultsFile = $fileName;
    !is_file($testConfigFile) && exit(1|echoPre('test config file not found: "'.$testConfigFile.'"'));
}
else exit(1|echoPre('unsupported file: "'.$fileName.'" (see -h for help)'));

// (1.3) check read access
!is_readable($testConfigFile ) && exit(1|echoPre('file not readable: "'.$testConfigFile .'"'));
!is_readable($testResultsFile) && exit(1|echoPre('file not readable: "'.$testResultsFile.'"'));


// (2) install SIGINT handler (catches Ctrl-C)                                      // To execute destructors it is enough to
if (!WINDOWS) pcntl_signal(SIGINT, create_function('$signal', 'exit();'));          // call exit() in the handler.


// (3) process the files
processTestFiles() || exit(1);

exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Process the test files.
 *
 * @return bool - success status
 */
function processTestFiles() {
    global $testConfigFile, $testResultsFile, $verbose;

    $test = Test::create($testConfigFile, $testResultsFile);
    $test->save();
    echoPre('Test(id='.$test->getId().') of "'.$test->getStrategy().'" with '.$test->countTrades().' trades saved.');
    return true;

    echoPre($test);

    $test->setReportingId($test->getReportingId() + 1);
    $test->save();
    echoPre('Test(id='.$test->getId().') of "'.$test->getStrategy().'" updated.');
    echoPre($test);
    return true;
}


/**
 * Show help screen.
 *
 * @param  string $message - additional message to display (default: none)
 */
function help($message = null) {
    if (is_null($message))
        $message = 'Save a test with its trade history in the database.';
    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP_MESSAGE
$message

  Syntax:  $self  [OPTIONS] FILE

  Options:  -v  Verbose output.
                -h  This help screen.

  FILE - test config (.ini) or test result (.log) file


HELP_MESSAGE;
}
