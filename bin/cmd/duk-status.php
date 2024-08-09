#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to show and update locally stored Dukascopy history start times.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\DukascopyHistoryStartCommand;

/** @var Application $app */
$app = require(__DIR__.'/../../app/init.php');

$app->addCommand(new DukascopyHistoryStartCommand());
$status = $app->run();

exit($status);
