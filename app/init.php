<?php
use phalcon\Loader as ClassLoader;

use rosasurfer\MiniStruts;
use rosasurfer\util\PHP;


// define application root and project helpers
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');
include(APPLICATION_ROOT.'/app/definitions.php');


// register MiniStruts and Phalcon class loader
require(APPLICATION_ROOT.'/etc/vendor/rosasurfer/ministruts/src/load.php');
(new ClassLoader())->registerClasses(include(__DIR__.'/classmap.php'))->register();


// error logging
error_reporting(E_ALL & ~E_DEPRECATED);
PHP::ini_set('error_log', APPLICATION_ROOT.'/etc/log/php_error.log');


// initialize MiniStruts
$options = [
   'config'            => APPLICATION_ROOT.'/app/config',
   'handle-errors'     => MiniStruts::THROW_EXCEPTIONS,
   'handle-exceptions' => true,
   'global-helpers'    => true,
];
MiniStruts::init($options);
