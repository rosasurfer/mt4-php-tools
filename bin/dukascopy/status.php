#!/usr/bin/env php
<?php
/**
 * Console application for displaying and updating Dukascopy history status.
 */
use rosasurfer\Application;
use rosasurfer\rt\console\DukascopyHistoryStatusCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new DukascopyHistoryStatusCommand());
$status = $app->run();

exit($status);
