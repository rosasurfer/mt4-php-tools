#!/usr/bin/env php
<?php
/**
 * Console command to manage the application's history.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\RosatraderHistoryCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new RosatraderHistoryCommand());
$status = $app->run();

exit($status);
