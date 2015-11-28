<?php
define('APPLICATION_NAME', 'myfx.pewasoft');                               // APPLICATION_ROOT: {project}/src
define('APPLICATION_ROOT', join(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, dirName(__FILE__)), 0, -1)));

ini_set('include_path',      APPLICATION_ROOT.'/WEB-INF');                 // WEB-INF-Verzeichnis einbinden
ini_set('session.save_path', APPLICATION_ROOT.'/../etc/tmp');
ini_set('apd.dumpdir',       APPLICATION_ROOT.'/../etc/tmp');
ini_set('error_log',         APPLICATION_ROOT.'/../etc/log/php_error_log');

require(APPLICATION_ROOT.'/../../phplib/src/phpLib.php');                  // PHPLib laden
include(APPLICATION_ROOT.'/WEB-INF/include/defines.php');                  // zusätzliche Definitionen laden
include(APPLICATION_ROOT.'/WEB-INF/classes/classes.php');                  // zusätzliche Klasen laden


// kein Time-Limit, falls wir in einer Shell laufen
if (!isSet($_SERVER['REQUEST_METHOD']))
   set_time_limit(0);
