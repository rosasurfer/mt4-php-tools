#!/usr/bin/env php
<?php
/**
 * CAUTION: Command line script to execute arbitrary PHP code in the context of the application.
 *          Used to read and return application configuration values to regular shell scripts.
 *
 * @example
 *   $ bin/phpcmd.php 'echo rosasurfer\config\Config::getDefault()["app.dir.log"];'
 */
require(dirName(realPath(__FILE__)).'/../app/init.php');
!CLI && exit(1|stderror('error: This script must be executed in CLI mode.'));


$args = array_slice($_SERVER['argv'], 1);
!$args && exit(0);

eval($args[0]);
