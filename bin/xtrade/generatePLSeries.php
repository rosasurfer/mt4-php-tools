#!/usr/bin/env php
<?php
/**
 *
 */
namespace rosasurfer\xtrade\generate_pl_series;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// --- Configuration --------------------------------------------------------------------------------------------------------



// --- Start ----------------------------------------------------------------------------------------------------------------


exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Show a basic help message.
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
