<?php
if (PHP_VERSION_ID < 50600) exit('[FATAL] This application requires PHP >= 5.6');

use rosasurfer\ministruts\StrutsController;

// configure and init the web app
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');
require(APPLICATION_ROOT.'/app/init.php');

// run the web app
StrutsController::processRequest();
