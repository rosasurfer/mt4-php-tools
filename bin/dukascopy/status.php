#!/usr/bin/env php
<?php
/**
 * Console application for displaying and updating Dukascopy history start times.
 */
use rosasurfer\Application;
use rosasurfer\rt\console\DukascopyHistoryStartCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new DukascopyHistoryStartCommand());
$status = $app->run();

exit($status);
