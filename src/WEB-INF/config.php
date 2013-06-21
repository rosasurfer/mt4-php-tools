<?php
define('APPLICATION_NAME', 'myfx.pewasoft');                         // APPLICATION_ROOT: {project}/src
define('APPLICATION_ROOT', join(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, dirName(__FILE__)), 0, -2)));

if (!isSet($_SERVER['REQUEST_METHOD'])) {                            // kein Time-Limit, falls wir in einer Shell laufen
   set_time_limit(0);
}
ini_set('include_path', realPath(APPLICATION_ROOT.'/WEB-INF'));      // WEB-INF-Verzeichnis einbinden

require(APPLICATION_ROOT.'/../../php-lib/src/phpLib.php');           // PHPLib laden
include(APPLICATION_ROOT.'/WEB-INF/include/defines.php');
include(APPLICATION_ROOT.'/WEB-INF/classes/classes.php');
?>
