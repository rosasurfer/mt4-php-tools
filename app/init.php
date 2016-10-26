<?php
use phalcon\Loader as ClassLoader;
use rosasurfer\MiniStruts;


// check app configuration
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirname(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('error_log', APPLICATION_ROOT.'/etc/log/php_error.log');
!isSet($_SERVER['REQUEST_METHOD']) && set_time_limit(0);          // no time limit for CLI


// configure and load Ministruts
require(APPLICATION_ROOT.'/etc/vendor/rosasurfer/ministruts/src/load.php');
$options = [
   'global-helpers'    => true,
   'handle-errors'     => MiniStruts::THROW_EXCEPTIONS,
   'handle-exceptions' => true,
];
MiniStruts::init($options);


// register class loader                                          TODO: replace with case-insensitive loader
(new ClassLoader())->registerClasses(include(__DIR__.'/classmap.php'))
                   ->register();

// load project definitions
include(APPLICATION_ROOT.'/app/definitions.php');
