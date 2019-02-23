#!/usr/bin/env php
<?php
/**
 * Command line tool for manipulating the status of Dukascopy symbols.
 */
namespace rosasurfer\rt\bin\dukascopy\status;

use rosasurfer\rt\model\DukascopySymbol;

require(dirname(realpath(__FILE__)).'/../../app/init.php');


// process instruments
$symbols = DukascopySymbol::dao()->findAll('select * from :DukascopySymbol order by name');

foreach ($symbols as $symbol) {
    $symbol->showHistoryStatus();
}
