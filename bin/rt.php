#!/usr/bin/env php
<?php
/**
 * Rosatrader console application.
 */
namespace rosasurfer\rt;

use rosasurfer\Application;
use rosasurfer\console\Command;

/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../app/init.php');

$app->addCommand(new Command());
$status = $app->run();

exit($status);
