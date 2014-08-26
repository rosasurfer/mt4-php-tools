#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die lokalen Daten mit denen des angegebenen Signals.  Die lokalen Daten können sich in einer Datenbank
 * oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt und eine Mail oder SMS
 * verschickt werden.
 */
require(dirName(__FILE__).'/../config.php');


// -- Start ----------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenparameter holen
$args = array_slice($_SERVER['argv'], 1);
if (!$args) {
   exit("\n  Syntax: ".baseName($_SERVER['PHP_SELF'])."  [dayfox|smartscalper]\n");
}
?>
