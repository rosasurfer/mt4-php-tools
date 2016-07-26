<?php
if (PHP_VERSION < '5.6') {
   echo('application error'.PHP_EOL);
   error_log('Error: A PHP version >= 5.6 is required (found version '.PHP_VERSION.').');
   exit(1);
}
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirname(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID'  , 'myfx');


// web application specific settings
ini_set('session.save_path', APPLICATION_ROOT.'/etc/tmp');


// init web app
require(APPLICATION_ROOT.'/app/init.php');


// run web app
StrutsController::processRequest();
