#!/usr/bin/env php
<?php
/**
 * Update the M1 history of synthetic Rosatrader instruments.
 */
namespace rosasurfer\rt\update_synthetics_m1;

use rosasurfer\process\Process;
use rosasurfer\rt\model\RosaSymbol;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// (1) parse and validate CLI arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
    $arg=='-h' && exit(1|help());
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
    Process::dispatchSignals();                                                         // process Ctrl-C
}
exit(0);


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

 Usage:    $self [options] [SYMBOL...]

   SYMBOL  The symbols to update. Without an argument all synthetic symbols marked for automatic update are processed.

 Options:
   -h      This help message.


HELP;
}
