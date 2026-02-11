#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Console command to update locally stored builds of rosasurfer/mt4-mql-framework.
 */
use rosasurfer\ministruts\Application;
use rosasurfer\rt\console\UpdateMql4BuildsCommand;

/** @var Application $app */
$app = require(__DIR__.'/../../app/init.php');

$app->addCommand(new UpdateMql4BuildsCommand());
$status = $app->run();

exit($status);
