#!/usr/bin/env php
<?php
/**
 * Command line version of phpInfo()
 */
use rosasurfer\util\PHP;

require(dirname(realpath(__FILE__)).'/../../app/init.php');

PHP::phpinfo();
echo PHP_EOL.'loaded php.ini: "'.php_ini_loaded_file().'"'.PHP_EOL;
