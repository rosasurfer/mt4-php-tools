<?php
use rosasurfer\ministruts\StrutsController;

// configure and init the web app
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));
require(APPLICATION_ROOT.'/app/init.php');

// run the web app
StrutsController::processRequest();
