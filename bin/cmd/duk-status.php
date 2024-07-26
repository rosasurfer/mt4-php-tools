#!/usr/bin/env php
<?php
/**
 * Console command to show and update locally stored Dukascopy history start times.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\DukascopyHistoryStartCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new DukascopyHistoryStartCommand());
$status = $app->run();

exit($status);
