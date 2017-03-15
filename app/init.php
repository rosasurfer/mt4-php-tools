<?php
use rosasurfer\MiniStruts;

// global settings
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('error_log'      , __DIR__.'/../etc/log/php-error.log');
ini_set('default_charset', 'UTF-8'                            );
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));

// class loader
require(__DIR__.'/../etc/vendor/autoload.php');

// initialize application
MiniStruts::init([
   'config'  => __DIR__.'/config',
   'globals' => true,
]);
