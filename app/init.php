<?php
use rosasurfer\MiniStruts;
use rosasurfer\util\PHP;


// class loader
require(__DIR__.'/../etc/vendor/autoload.php');


// check app configuration
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));


// global settings
error_reporting(E_ALL & ~E_DEPRECATED);
PHP::ini_set('error_log'      , __DIR__.'/../etc/log/php-error.log');
PHP::ini_set('default_charset', 'UTF-8'                            );


// initialize MiniStruts
MiniStruts::init([
   'config'  => __DIR__.'/config',
   'globals' => true,
]);
