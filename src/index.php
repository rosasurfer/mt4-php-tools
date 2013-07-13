<?
/**
 * Zentraler HTTP-Request-Handler
 */
require(dirName(__FILE__).'/WEB-INF/config.php');

FrontController ::processRequest();

/*
$timezone    = new DateTimeZone('Europe/Minsk');
$transitions = $timezone->getTransitions();
echoPre($transitions);
*/
?>
