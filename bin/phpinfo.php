#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Command line version of phpInfo()
 */
namespace rosasurfer\rt\bin\admin;

use rosasurfer\ministruts\util\PHP;

require(dirname(realpath(__FILE__)).'/../app/init.php');

PHP::phpinfo();
echo PHP_EOL.'loaded php.ini: "'.php_ini_loaded_file().'"'.PHP_EOL;
