#!/usr/bin/php -Cq
<?php
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

// (1.1) Options: -l (Looping), -f (FileSyncOnly)
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   if (in_array($arg, array('-l','/l'))) { $looping     =true; unset($args[$i]); continue; }
   if (in_array($arg, array('-f','/f'))) { $fileSyncOnly=true; unset($args[$i]); continue; }
}

// (1.2) Signalnamen
!$args && $args=array_keys($signals);                       // ohne konkrete Namen werden alle Signale synchronisiert
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
      processSignal($arg, $fileSyncOnly);
   }
   if (!$looping) break;
   sleep($sleepSeconds);                                    // vorm nächsten Durchlauf jeweils einige Sek. schlafen
}
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 *
 * @param  string $signalAlias  - Signalalias
 * @param  bool   $fileSyncOnly - ob alle Daten oder nur die MT4-Dateien aktualisiert werden sollen
 */
function processSignal($signalAlias, $fileSyncOnly) {
   if (!is_string($signalAlias)) throw new IllegalTypeException('Illegal type of parameter $signalAlias: '.getType($signalAlias));
   if (!is_bool($fileSyncOnly)) throw new IllegalTypeException('Illegal type of parameter $fileSyncOnly: '.getType($fileSyncOnly));

   global $signals;
   $signalAlias = strToLower($signalAlias);
   $signalName  = $signals[$signalAlias];
   echo(str_pad($signalName.' ', 16, '.', STR_PAD_RIGHT).' ');

   $updates = false;                         // ob beim Synchronisieren Änderungen festgestellt wurden

   if (!$fileSyncOnly) {
      // HTML-Seite laden
      $content = SimpleTrader ::getSignalPage($signalAlias);

      // HTML-Seite parsen
      $openPositions = $closedPositions = array();
      SimpleTrader ::parseSignalData($signalAlias, $content, $openPositions, $closedPositions);

      // Datenbank aktualisieren
     $updates = updateDatabase($signalAlias, $openPositions, $closedPositions);
   }

   // Daten-Files aktualisieren (Datenbasis für MT4)
   $signal = Signal ::dao()->getByAlias($signalAlias);
   MT4 ::updateDataFiles($signal, $updates);
}


/**
 * Aktualisiert die lokalen offenen und geschlossenen Positionen.
 *
 * @param  string $signalAlias          - Signalalias
 * @param  array  $currentOpenPositions - Array mit aktuell offenen Positionen
 * @param  array  $currentHistory       - Array mit aktuellen Historydaten
 *
 * @return bool - ob Änderungen detektiert wurden oder nicht
 */
function updateDatabase($signalAlias, array &$currentOpenPositions, array &$currentHistory) {
   $updates                = false;
   $unchangedOpenPositions = 0;

   $db = OpenPosition ::dao()->getDB();
   $db->begin();
   try {
      // (1) lokalen Stand der offenen Positionen holen
      $knownOpenPositions = OpenPosition ::dao()->listBySignalAlias($signalAlias, $assocTicket=true);


      // (2) offene Positionen abgleichen (sind aufsteigend nach OpenTime+Ticket sortiert)
      foreach ($currentOpenPositions as $i => &$data) {
         $sTicket  = (string)$data['ticket'];
         $position = null;

         if (!isSet($knownOpenPositions[$sTicket])) {
            !$updates && ($updates=true) && echos("\n");
            SimpleTrader ::onPositionOpen(OpenPosition ::create($signalAlias, $data)->save());
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
               $openPosition = OpenPosition ::dao()->getByTicket($signalAlias, $ticket);
               unset($closedPositions[$sTicket]);
            }
         }

         if (!$openPosition && ClosedPosition ::dao()->isTicket($signalAlias, $ticket)) {
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
            ClosedPosition ::create($signalAlias, $data)->save();
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

   return $updates;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message - zusätzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message=null) {
   if (!is_null($message))
      echo($message."\n");

   $self = baseName($_SERVER['PHP_SELF']);

echo <<<END
 Syntax:  $self [-l] [-f] [signal_name ...]

 Options:  -l  Runs in a loop and synchronizes every 30 seconds.
           -f  Synchronizes files only, not the database (doesn't go online).

END;
}
?>
