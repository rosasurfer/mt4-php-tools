#!/usr/bin/env php
<?php
/**
 * Command line version of phpInfo()
 */
use rosasurfer\Application;
use rosasurfer\util\PHP;

require(dirName(realPath(__FILE__)).'/../app/init.php');

$app = new Application();                               // creating an application loads the configuration

PHP::phpInfo();
echo PHP_EOL.'loaded php.ini: "'.php_ini_loaded_file().'"'.PHP_EOL;
