<?php
if (PHP_VERSION_ID < 50600) exit('[FATAL]  This application requires PHP >= 5.6');

use rosasurfer\MiniStruts;
use rosasurfer\util\PHP;


// check app configuration
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');


// load Composer
require(APPLICATION_ROOT.'/etc/vendor/autoload.php');


// global settings
error_reporting(E_ALL & ~E_DEPRECATED);
PHP::ini_set('error_log'      , APPLICATION_ROOT.'/etc/log/php-error.log');
PHP::ini_set('default_charset', 'UTF-8'                                  );


// initialize MiniStruts
$options = [
   'config'            => APPLICATION_ROOT.'/app/config',
   'handle-errors'     => MiniStruts::THROW_EXCEPTIONS,
   'handle-exceptions' => true,
   'global-helpers'    => true,
];
MiniStruts::init($options);
