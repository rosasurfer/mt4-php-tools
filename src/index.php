<?
define('APPLICATION_NAME', 'myfx.pewasoft');
define('APPLICATION_ROOT',  dirName(__FILE__));                      // APPLICATION_ROOT: {project}/src

// PHPLib, Definitionen und Klassen einbinden
require(dirName(__FILE__).'/../../php-lib/src/phpLib.php');
include(dirName(__FILE__).'/WEB-INF/include/defines.php');
include(dirName(__FILE__).'/WEB-INF/classes/classes.php');


FrontController ::processRequest();
?>
