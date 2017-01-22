#!/usr/bin/php
<?php
/**
 * Save a test with its trade history in the database.
 */
require(__DIR__.'/../../app/init.php');
date_default_timezone_set('GMT');


// --- Configuration ----------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// --- Start ------------------------------------------------------------------------------------------------------------------


// (1) read and validate command line arguments
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
   if ($arg == '-h')   exit(1|help());                                              // help
   if ($arg == '-v') { $verbose = max($verbose, 1); unset($args[$i]); continue; }   // verbose output
}

// we expect exactly one argument, a filename
sizeOf($args)!=1 && exit(1|help());
$fileName = array_shift($args);
!is_file($fileName) && exit(1|echoPre('file not found "'.$fileName.'"'));


// (2) install SIGINT handler (catches Ctrl-C)                                      // To execute destructors it is enough to
if (!WINDOWS) pcntl_signal(SIGINT, create_function('$signal', 'exit(0);'));         // call exit() in the handler.


// (3) do something useful


exit(0);


// --- Functions --------------------------------------------------------------------------------------------------------------


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


HELP_MESSAGE;
}
