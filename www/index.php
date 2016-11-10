<?php
use rosasurfer\ministruts\StrutsController;

// configure and init web app
!defined('APPLICATION_ROOT') && define('APPLICATION_ROOT', dirName(__DIR__));
!defined('APPLICATION_ID'  ) && define('APPLICATION_ID',  'myfx');

if (!PHP::ini_set($o='session.save_path', APPLICATION_ROOT.'/etc/tmp')) throw new RuntimeException('Cannot set php.ini option "'.$o.'" (former value="'.ini_get($o).'")');
require(APPLICATION_ROOT.'/app/init.php');


// run web app
StrutsController::processRequest();
