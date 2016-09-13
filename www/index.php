<?php
use rosasurfer\ministruts\StrutsController;

// configure and init web app
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');

ini_set('session.save_path', APPLICATION_ROOT.'/etc/tmp');
require(APPLICATION_ROOT.'/app/init.php');


// run web app
StrutsController::processRequest();
