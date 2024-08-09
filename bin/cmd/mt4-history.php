#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to create, update or show status of MT4 history files.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\MetaTraderHistoryCommand;

/** @var Application $app */
$app = require(__DIR__.'/../../app/init.php');

$app->addCommand(new MetaTraderHistoryCommand());
$status = $app->run();

exit($status);
