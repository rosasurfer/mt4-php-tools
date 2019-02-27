#!/usr/bin/env php
<?php
/**
 * Console application for processing Rosatrader history.
 */
namespace rosasurfer\rt\bin\history;

use rosasurfer\process\Process;
use rosasurfer\rt\model\RosaSymbol;

require(dirname(realpath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h')   exit(1);
    if ($arg[0] == '-') unset($args[$i]);                               // drop unknown options
}

// parse command
$cmd = array_shift($args);
if (!in_array($cmd, ['status', 'synchronize'])) exit(1);

/** @var RosaSymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol) exit(1|stderr('error: unknown Rosatrader symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                             // using the name as index removes duplicates
}                                                                       // if none was specified process all
$symbols = $symbols ?: RosaSymbol::dao()->findAll('select * from :RosaSymbol order by name');
!$symbols && echoPre('No Rosatrader instruments found.');


// process instruments
foreach ($symbols as $symbol) {
    if ($cmd == 'status'     ) $symbol->showHistoryStatus();
    if ($cmd == 'synchronize') $symbol->synchronizeHistory();
    Process::dispatchSignals();                                         // process Ctrl-C
}
exit(0);
