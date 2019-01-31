#!/usr/bin/env php
<?php
/**
 * Update the M1 history of synthetic Rosatrader instruments.
 *
 * @see  https://github.com/rosasurfer/mt4-tools/blob/master/app/lib/synthetic/README.md
 */
namespace rosasurfer\rt\update_synthetics_m1;

use rosasurfer\process\Process;
use rosasurfer\rt\model\RosaSymbol;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- configuration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                               // output verbosity


// -- start -----------------------------------------------------------------------------------------------------------------


// (1) parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    if ($arg == '-h'  )   exit(1|help());                                               // help
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; }    // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; }    // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; }    // very verbose output
}

/** @var RosaSymbol[] $symbols */
$symbols = [];

// parse symbols
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol)                exit(1|stderror('error: unknown symbol "'.$args[$i].'"'));
    if (!$symbol->isSynthetic()) exit(1|stderror('error: not a synthetic instrument "'.$symbol->getName().'"'));
    $symbols[$symbol->getName()] = $symbol;                                             // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAllByType(RosaSymbol::TYPE_SYNTHETIC);    // if none is specified update all synthetics
!$symbols && echoPre('no synthetic instruments found');


// (2) update instruments
foreach ($symbols as $symbol) {
    if ($symbol->updateHistory())
        echoPre('[Ok]      '.$symbol->getName());
    Process::dispatchSignals();                                                         // check for Ctrl-C
}
exit(0);


// --- functions ------------------------------------------------------------------------------------------------------------


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message = null) {
    if (isSet($message))
        echo $message.NL.NL;

    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
 Update the M1 history of the specified synthetic symbols.

 Syntax:  $self [SYMBOL...]

   SYMBOL    One or more symbols to update. Without a symbol all defined synthetic symbols are updated.

   Options:  -v    Verbose output.
             -vv   More verbose output.
             -vvv  Very verbose output.
             -h    This help screen.


HELP;
}
