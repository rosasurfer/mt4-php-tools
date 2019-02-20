#!/usr/bin/env php
<?php
/**
 * Command line tool for manipulating the status of Dukascopy symbols.
 */
namespace rosasurfer\rt\bin\dukascopy\status;

use rosasurfer\Application;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\model\DukascopySymbol;

require(dirname(realpath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


$showLocal = true;


// parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h')   exit(1|help());
    if ($arg == '-r')   $showLocal = false;
    if ($arg[0] == '-') unset($args[$i]);                               // drop unknown options
}

// parse command
$cmd = array_shift($args);
if (!in_array($cmd, ['show', 'pull'])) exit(1|help());

/** @var DukascopySymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var DukascopySymbol $symbol */
    $symbol = DukascopySymbol::dao()->findByName($arg);
    if (!$symbol) exit(1|stderror('error: unknown or untracked Dukascopy symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                             // using the name as index removes duplicates
}

// process instruments
if ($symbols || $showLocal) {
    !$symbols && $symbols = DukascopySymbol::dao()->findAll('select * from :DukascopySymbol order by name');
    foreach ($symbols as $symbol) {
        if ($cmd == 'show') $symbol->showHistoryStatus($showLocal);
        Process::dispatchSignals();                                     // process Ctrl-C
    }
    !$symbols && echoPre('No Dukascopy instruments found.');
}
else {
    echoPre('[Info]    fetching history start times of all available symbols');
    /** @var Dukascopy $dukascopy */
    $dukascopy = Application::getDi()['dukascopy'];
    $starttimes = $dukascopy->fetchHistoryStarts();
}
exit(0);


/**
 * Help
 *
 * @param  string $message [optional] - additional message to display (default: none)
 */
function help($message = null) {
    if (isset($message))
        echo $message.NL.NL;

    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
Process the history status of the specified Dukascopy symbols.

Syntax:  $self <command> [options] [SYMBOL...]

 Commands:
   show         Show available history start times.
   pull         Synchronize local history status with current data from Dukascopy.

 Options:
   -r           Show remote instead of local history start times.
   -h           This help screen.

 SYMBOL         The Dukascopy symbols to process. Without a symbol all locally tracked symbols are processed.


HELP;
}
