#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die Daten ein oder mehrerer Signale mit den lokal gespeicherten Daten.  Die lokalen Daten können
 * sich in einer Datenbank oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt
 * und eine Mail oder SMS verschickt werden.
 *
 *
 *
 */
require(dirName(realPath(__FILE__)).'/../config.php');


// Länge der Pause zwischen zwei Updates
$sleepSeconds = 30;


// zur Zeit unterstützte Signale
$signals = array('alexprofit'   => 'AlexProfit',
                 'caesar2'      => 'Caesar2',
                 'caesar21'     => 'Caesar2.1',
                 'dayfox'       => 'DayFox',
                 'goldstar'     => 'GoldStar',
                 'smartscalper' => 'SmartScalper',
                 'smarttrader'  => 'SmartTrader',
                );


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// (1.1) looping
$looping = false;
foreach ($args as $i => $arg) {
   if (in_array(strToLower($arg), array('-l','/l'))) {
      $looping = true;
      unset($args[$i]);
   }
}

// (1.2) Signalnamen
!$args && $args=array_keys($signals);                       // ohne Parameter werden alle Signale synchronisiert
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   in_array($arg, array('-?','/?','-h','/h','-help','/help')) && exit(1|help());
   !array_key_exists($arg, $signals)                          && exit(1|help('Unknown signal: '.$args[$i]));
   $args[$i] = $arg;
}
$args = array_unique($args);


// (2) Erreichbarkeit der Datenbank prüfen
try { Signal ::dao()->getDB()->executeSql("select 1 from dual"); }
catch (Exception $ex) {
   if ($ex instanceof InfrastructureException)
      exit(1|echoPre('error: '.$ex->getMessage()));         // Can not connect to MySQL server on 'localhost:3306'
   throw $ex;
}


// (3) Signale aktualisieren
while (true) {
   foreach ($args as $i => $arg) {
      processSignal($arg);
   }
   if (!$looping) break;
   sleep($sleepSeconds);                                    // vorm nächsten Durchlauf jeweils einige Sek. schlafen
}
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 *
 * @param  string $signalAlias - Signalalias
 */
function processSignal($signalAlias) {
   // Parametervalidierung
   if (!is_string($signalAlias)) throw new IllegalTypeException('Illegal type of parameter $signalAlias: '.getType($signalAlias));
   $signalAlias = strToLower($signalAlias);

   global $signals;
   $signalName = $signals[$signalAlias];
   echo(str_pad($signalName.' ', 16, '.', STR_PAD_RIGHT).' ');

   // HTML-Seite laden
   $content = SimpleTrader ::getSignalPage($signalAlias);

   // HTML-Seite parsen
   $openPositions = $closedPositions = array();
   SimpleTrader ::parseSignalData($signalAlias, $content, $openPositions, $closedPositions);

   // Datenbank aktualisieren
   updateTrades($signalAlias, $openPositions, $closedPositions);

/*
Syncing signal GoldStar...
[FATAL] Uncaught IOException: CURL error CURLE_OPERATION_TIMEDOUT (Operation timed out after 30 seconds with 2593 bytes received), url: http://cp.forexsignals.com/signal/2622/signal.html
in /var/www/php-lib/src/php/net/http/CurlHttpClient.php on line 165

Stacktrace:
-----------
CurlHttpClient->send()  # line 165, file: /var/www/php-lib/src/php/net/http/CurlHttpClient.php
processSignal()         # line 150, file: /var/www/mt4.rosasurfer.com/src/WEB-INF/bin/syncSignals.php
main()                  # line 85,  file: /var/www/mt4.rosasurfer.com/src/WEB-INF/bin/syncSignals.php

PHP [FATAL] Uncaught IOException: CURL error CURLE_OPERATION_TIMEDOUT (Operation timed out after 30 seconds with 2593 bytes received), url: http://cp.forexsignals.com/signal/2622/signal.html in /var/www/php-lib/src/php/net/http/CurlHttpClient.php on line 165
*/
}


/**
 * Aktualisiert die offenen und geschlossenen Positionen.
 *
 * @param  string $signal               - Signal
 * @param  array  $currentOpenPositions - Array mit aktuell offenen Positionen
 * @param  array  $currentHistory       - Array mit aktuellen Historydaten
 */
function updateTrades($signal, array &$currentOpenPositions, array &$currentHistory) {
   $updates                = false;
   $unchangedOpenPositions = 0;

   $db = OpenPosition ::dao()->getDB();
   $db->begin();
   try {
      // (1) letzten bekannten Stand der offenen Positionen holen
      $knownOpenPositions = OpenPosition ::dao()->listBySignalAlias($signal, $assocTicket=true);


      // (2) offene Positionen abgleichen (sind aufsteigend nach OpenTime+Ticket sortiert)
      foreach ($currentOpenPositions as $i => &$data) {
         $sTicket  = (string)$data['ticket'];
         $position = null;

         if (!isSet($knownOpenPositions[$sTicket])) {
            !$updates && ($updates=true) && echos("\n");
            SimpleTrader ::onPositionOpen(OpenPosition ::create($signal, $data)->save());
         }
         else {
            // auf modifiziertes TP- oder SL-Limit prüfen
            if ($data['takeprofit'] != ($prevTP=$knownOpenPositions[$sTicket]->getTakeProfit())) $position = $knownOpenPositions[$sTicket]->setTakeProfit($data['takeprofit']);
            if ($data['stoploss'  ] != ($prevSL=$knownOpenPositions[$sTicket]->getStopLoss())  ) $position = $knownOpenPositions[$sTicket]->setStopLoss  ($data['stoploss'  ]);
            if ($position) {
               !$updates && ($updates=true) && echos("\n");
               SimpleTrader ::onPositionModify($position->save(), $prevTP, $prevSL);
            }
            else $unchangedOpenPositions++;
            unset($knownOpenPositions[$sTicket]);              // geprüfte Position aus Liste löschen
         }
      }


      // (3) History abgleichen (ist aufsteigend nach CloseTime+OpenTime+Ticket sortiert)
      $closedPositions   = $knownOpenPositions;                // alle in $knownOpenPositions übrig gebliebenen Positionen müssen geschlossen worden sein
      $hstSize           = sizeOf($currentHistory);
      $matchingPositions = $otherClosedPositions = 0;          // nach 3 übereinstimmenden Historyeinträgen wird das Update abgebrochen
      $openGotClosed     = false;

      for ($i=$hstSize-1; $i >= 0; $i--) {                     // History ist aufsteigend sortiert, wird rückwärts verarbeitet und bricht bei Übereinstimmung
         $data         = $currentHistory[$i];                  // der Daten ab (schnellste Variante)
         $ticket       = $data['ticket'];
         $openPosition = null;

         if ($closedPositions) {
            $sTicket = (string) $ticket;
            if (isSet($closedPositions[$sTicket])) {
               $openPosition = OpenPosition ::dao()->getByTicket($signal, $ticket);
               unset($closedPositions[$sTicket]);
            }
         }

         if (!$openPosition && ClosedPosition ::dao()->isTicket($signal, $ticket)) {
            $matchingPositions++;
            if ($matchingPositions >= 3)
               break;
            continue;
         }

         // Position in t_closedposition einfügen
         !$updates && ($updates=true) && echos("\n");
         if ($openPosition) {
            $closedPosition = ClosedPosition ::create($openPosition, $data)->save();
            $openPosition->delete();                           // vormals offene Position aus t_openposition löschen
            SimpleTrader ::onPositionClose($closedPosition);
            $openGotClosed = true;
         }
         else {
            ClosedPosition ::create($signal, $data)->save();
            $otherClosedPositions++;
         }
      }
      !$updates             && echoPre('no changes'.($unchangedOpenPositions ? ' ('.$unchangedOpenPositions.' open position'.($unchangedOpenPositions==1 ? '':'s').')':''));
      $otherClosedPositions && echoPre($otherClosedPositions.' '.(!$openGotClosed ? 'new':'other').' closed position'.($otherClosedPositions==1 ? '':'s'));

      if ($closedPositions) throw new plRuntimeException('Found '.sizeOf($closedPositions).' orphaned open position'.(sizeOf($closedPositions)==1 ? '':'s').":\n".printFormatted($closedPositions, true));


      // (4) alles speichern
      $db->commit();
   }
   catch (Exception $ex) {
      $db->rollback();
      throw $ex;
   }
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n");
   global $signals;
   echo("\n  Syntax: ".baseName($_SERVER['PHP_SELF'])."  [".implode('|', array_keys($signals))."]\n");
}

//$start = $stop = microtime(true);
//echoPre('Execution took '.number_format($stop-$start, 3).' sec');
?>
