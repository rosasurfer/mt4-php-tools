#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to scale the bars of a MetaTrader4 history file.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\ScaleHistoryCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new ScaleHistoryCommand());
$status = $app->run();

exit($status);
