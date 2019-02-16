#!/usr/bin/env php
<?php
/**
 * Rosatrader console application
 */
namespace rosasurfer\rt;

use rosasurfer\Application;
use rosasurfer\rt\console\TestCommand;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../app/init.php');

$app->addCommand($c = new TestCommand());

echoPre($app);

$status = $app->run();
exit($status);

