#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * TODO: replace by Ministruts console command
 *
 *
 * Update the M1 history of synthetic Rosatrader instruments.
 */
namespace rosasurfer\rt\cmd\app_update_synthetic_bars;

use rosasurfer\ministruts\process\Process;
use rosasurfer\rt\model\RosaSymbol;

use function rosasurfer\ministruts\echof;
use function rosasurfer\ministruts\stderr;

use const rosasurfer\ministruts\NL;

require(dirname(realpath(__FILE__)).'/../../../app/init.php');
date_default_timezone_set('GMT');


// parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg=='-h') {
        help();
        exit(1);
    }
}

/** @var RosaSymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol) {
        stderr('error: unknown symbol "'.$args[$i].'"');
        exit(1);
    }
    if (!$symbol->isSynthetic()) {
        stderr('error: not a synthetic instrument "'.$symbol->getName().'"');
        exit(1);
    }
    $symbols[$symbol->getName()] = $symbol;                                             // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAllByType(RosaSymbol::TYPE_SYNTHETIC);    // if none is specified update all synthetics
!$symbols && echof('No synthetic instruments found.');


// update instruments
foreach ($symbols as $symbol) {
    $symbol->updateHistory();
    Process::dispatchSignals();                                                         // process Ctrl-C
}
exit(0);


/**
 * Help
 *
 * @param  ?string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 *
 * @return void
 */
function help($message = null) {
    if (isset($message))
        echo $message.NL.NL;

    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP
Update the M1 history of the specified synthetic symbols.

 Usage:    $self [options] [SYMBOL...]

   SYMBOL  The symbols to update. Without an argument all synthetic symbols marked for automatic update are processed.

 Options:
   -h      This help message.


HELP;
}
