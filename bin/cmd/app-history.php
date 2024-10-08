#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to manage the application's history.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\RosatraderHistoryCommand;

/** @var Application $app */
$app = require(__DIR__.'/../../app/init.php');

$app->addCommand(new RosatraderHistoryCommand());
$status = $app->run();

exit($status);
