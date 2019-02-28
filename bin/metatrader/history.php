#!/usr/bin/env php
<?php
/**
 * Console application for working with MetaTrader history files.
 */
use rosasurfer\Application;
use rosasurfer\rt\console\MetaTraderHistoryCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../../app/init.php');

$app->addCommand(new MetaTraderHistoryCommand());
$status = $app->run();

exit($status);
