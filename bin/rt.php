#!/usr/bin/env php
<?php
/**
 * Rosatrader console application
 */
namespace rosasurfer\rt;

use rosasurfer\Application;
use rosasurfer\console\HelpCommand;
use rosasurfer\console\ListCommand;

use rosasurfer\rt\view\TestCommand;


/** @var Application $app */
$app = require(dirname(realpath(__FILE__)).'/../app/init.php');

$app->addCommand(new HelpCommand());
$app->addCommand(new ListCommand());
$app->addCommand(new TestCommand());

echoPre($app);

$status = $app->run();
exit($status);

