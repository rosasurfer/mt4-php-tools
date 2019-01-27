#!/usr/bin/env php
<?php
/**
 * Command line tool for processing Rosatrader history.
 */
namespace rosasurfer\rt\history;

use rosasurfer\process\Process;
use rosasurfer\rt\model\RosaSymbol;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// configuration
$verbose = 0;                                       // output verbosity


// (1) parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h'  )   exit(1|help());
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; }
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; }
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; }
}
// drop unknown options
foreach ($args as $i => $arg) {
    if ($arg[0] == '-')
        unset($args[$i]);
}
// parse command
$cmd = array_shift($args);
if (!in_array($cmd, ['status', 'synchronize', 'refresh'])) exit(1|help());

/** @var RosaSymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol) exit(1|stderror('error: unknown symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                                                     // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAll('select * from :RosaSymbol order by name');   // process all if none was specified
!$symbols && echoPre('no instruments found');


// (2) process instruments
foreach ($symbols as $symbol) {
    if ($cmd == 'status'     ) $symbol->showHistoryStatus();
    if ($cmd == 'synchronize') $symbol->synchronizeHistory();
    if ($cmd == 'refresh'    ) $symbol->refreshHistory();

    Process::dispatchSignals();                                     // check for Ctrl-C
}
exit(0);


/**
 * Display help screen and syntax.
 *
 * @param  string $message [optional] - additional message to display (default: none)
 */
function help($message = null) {
    if (isSet($message))
        echo $message.NL.NL;

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
 Process the history of the specified Rosatrader symbols.

 Syntax:  $self <command> [options] [SYMBOL...]

   Commands: status       Show history status information.
             synchronize  Synchronize the history in the file system with the database.
             refresh      Discard the existing history and reload/recreate it.

   Options:  -v           Verbose output.
             -vv          More verbose output.
             -vvv         Very verbose output.
             -h           This help screen.

   SYMBOL    The symbols to process. Without a symbol all symbols are processed.


HELP;
}
