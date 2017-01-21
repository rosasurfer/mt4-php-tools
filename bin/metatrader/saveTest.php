#!/usr/bin/php
<?php
/**
 * Save a test and its trade data in the database.
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


// (2) install SIGINT handler                                                       // To execute destructors on Ctrl-C it is
if (!WINDOWS) pcntl_signal(SIGINT, create_function('$signal', 'exit(0);'));         // enough to call exit() in the handler.


// (3) do something useful


exit(0);


// --- Functions --------------------------------------------------------------------------------------------------------------


/**
 * Show help screen with syntax.
 *
 * @param  string $message - additional message to display (default: none)
 */
function help($message = null) {
   if (!is_null($message))
      echo $message.NL.NL;

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END

  Syntax:  $self [OPTIONS ...]


END;
}
