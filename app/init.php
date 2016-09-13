<?php
// check/update app configuration
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirname(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('error_log', APPLICATION_ROOT.'/etc/log/php_error.log');
!isSet($_SERVER['REQUEST_METHOD']) && set_time_limit(0);          // no time limit for CLI


// load Ministruts
require(APPLICATION_ROOT.'/etc/vendor/rosasurfer/ministruts/src/load-global.php');


// register class loader                                          TODO: replace with case-insensitive loader
use phalcon\Loader as ClassLoader;
(new ClassLoader())->registerClasses(include(__DIR__.'/classmap.php'))
                   ->register();

// load project definitions
include(APPLICATION_ROOT.'/app/definitions.php');
