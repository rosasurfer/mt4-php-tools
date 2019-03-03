#!/usr/bin/env php
<?php
/**
 * Console application to manage the Rosatrader history.
 */
use rosasurfer\Application;
use rosasurfer\rt\console\RosatraderHistoryCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new RosatraderHistoryCommand());
$status = $app->run();

exit($status);
