#!/usr/bin/env php
<?php
/**
 * Console application to show or update Dukascopy history start times.
 */
use rosasurfer\Application;
use rosasurfer\rt\console\DukascopyHistoryStartCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new DukascopyHistoryStartCommand());
$status = $app->run();

exit($status);
