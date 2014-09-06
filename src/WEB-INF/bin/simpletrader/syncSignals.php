#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die lokalen Daten mit denen des angegebenen Signals.  Die lokalen Daten können sich in einer Datenbank
 * oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt und eine Mail oder SMS
 * verschickt werden.
 */
require(dirName(__FILE__).'/../config.php');


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// Namen aller zur Zeit unterstützten Signale
$knownSignals = array('dayfox', 'smarttrader', 'smartscalper');


// Befehlszeilenparameter einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
if (sizeOf($args) != 1)                             exit(1|help());
if (!in_array(strToLower($args[0]), $knownSignals)) exit(1|help('Unknown signal: '.$args[0]));


// Signal verarbeiten
processSignal($args[0]);
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 *
 * @param  string $signal - Signal-Name
 */
function processSignal($signal) {
   // Parametervalidierung
   if (!is_string($signal)) throw new IllegalTypeException('Illegal type of parameter $signal: '.getType($signal));
   $signal = strToLower($signal);

   echoPre('syncing signal '.$signal.'...');
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n");
   global $knownSignals;
   echo("\n  Syntax: ".baseName($_SERVER['PHP_SELF'])."  [".implode('|', $knownSignals)."]\n");
}
?>
