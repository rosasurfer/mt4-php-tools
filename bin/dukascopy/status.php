#!/usr/bin/env php
<?php
/**
 * Command line tool for manipulating the status of Dukascopy symbols.
 */
namespace rosasurfer\rt\dukascopy\status;

use rosasurfer\process\Process;
use rosasurfer\rt\model\DukascopySymbol;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h')   exit(1|help());
    if ($arg[0] == '-') unset($args[$i]);                               // drop unknown options
}

// parse command
$cmd = array_shift($args);
if (!in_array($cmd, ['show', 'synchronize'])) exit(1|help());

/** @var DukascopySymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var DukascopySymbol $symbol */
    $symbol = DukascopySymbol::dao()->findByName($arg);
    if (!$symbol) exit(1|stderror('error: untracked Dukascopy symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                             // using the name as index removes duplicates
}                                                                       // if none was specified process all
$symbols = $symbols ?: DukascopySymbol::dao()->findAll('select * from :DukascopySymbol order by name');
!$symbols && echoPre('No Dukascopy instruments found.');


// process instruments
foreach ($symbols as $symbol) {
    if ($cmd == 'show'       ) $symbol->showHistoryStatus();
  //if ($cmd == 'synchronize') $symbol->synchronizeHistoryStatus();
    Process::dispatchSignals();                                         // process Ctrl-C
}
exit(0);


/**
 * Help
 *
 * @param  string $message [optional] - additional message to display (default: none)
 */
function help($message = null) {
    if (isSet($message))
        echo $message.NL.NL;

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
Manipulate the history status of the specified Dukascopy symbols.

Syntax:  $self <command> [options] [SYMBOL...]

 Commands:
   show         Show local history status information.
   synchronize  Synchronize local history status with current data from Dukascopy.

 Options:
   -h           This help screen.

 SYMBOL         The Dukascopy symbols to process. Without a symbol all locally tracked symbols are processed.


HELP;
}
