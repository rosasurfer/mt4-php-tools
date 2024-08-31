#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to process MT4 "symbols.raw" files.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\MetaTraderSymbolsCommand;

/** @var Application $app */
$app = require(__DIR__.'/../../app/init.php');

$app->addCommand(new MetaTraderSymbolsCommand());
$status = $app->run();

exit($status);
