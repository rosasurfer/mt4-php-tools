#!/usr/bin/env php
<?php
/**
 * Console command to process MetaTrader "symbols.raw" files.
 */
use rosasurfer\Application;
use rosasurfer\rt\console\MetaTraderSymbolsCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new MetaTraderSymbolsCommand());
$status = $app->run();

exit($status);
