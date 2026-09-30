#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to convert a Topstep account's trade history from "Dashboard" JSON format to TopstepX's CSV format.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\TopstepConvertHistoryCommand;

/** @var Application $app */
$app = require(__DIR__.'/../../app/init.php');

$app->addCommand(new TopstepConvertHistoryCommand());
$status = $app->run();

exit($status);
