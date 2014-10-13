#!/usr/bin/php -Cq
<?php
/**
 * Synchronisiert die Daten ein oder mehrerer Signale mit den lokal gespeicherten Daten (Datenbank und MT4-Datenfiles).
 * Bei Datenänderung wird eine Mail und eine SMS verschickt.
 *
 *
 *
 */
require(dirName(realPath(__FILE__)).'/../config.php');


$sleepSeconds      = 30;         // Länge der Pause zwischen zwei Updates
$signalNamePadding = 16;         // Padding der Anzeige des Signalnamens:  @see function processSignal()


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);

// (1.1) Optionen
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   if (in_array($arg, array('-?','/?','-h','/h','-help','/help'))) exit(1|help());              // Hilfe
   if (in_array($arg, array('-l','/l'))) { $looping     =true; unset($args[$i]); continue; }    // -l=Looping
   if (in_array($arg, array('-f','/f'))) { $fileSyncOnly=true; unset($args[$i]); continue; }    // -f=FileSyncOnly
}

// (1.2) Signalnamen
foreach ($args as $i => $arg) {
   if ($arg == '*') {                                       // * ist Wildcard für alle Signale
      $args = array('*');
      break;
   }
   $args[$i] = strToLower($arg);
}
$args = $args ? array_unique($args) : array('*');           // ohne angegebene Namen werden alle Signale synchronisiert


// (2) Erreichbarkeit der Datenbank prüfen
try { Signal ::dao()->getDB()->executeSql("select 1 from dual"); }
catch (Exception $ex) {
   if ($ex instanceof InfrastructureException)
      exit(1|echoPre('error: '.$ex->getMessage()));         // Can not connect to MySQL server on 'localhost:3306'
   throw $ex;
}


// (3) Signale aktualisieren
while (true) {
   foreach ($args as $i => $arg)
      processSignal($arg, $fileSyncOnly);
   if (!$looping) break;
   sleep($sleepSeconds);                                    // vorm nächsten Durchlauf jeweils einige Sek. schlafen
}


// (4) Ende
exit(0);


// --- Funktionen ---------------------------------------------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die Daten eines Signals.
 *
 * @param  string $alias        - Signalalias
 * @param  bool   $fileSyncOnly - ob alle Daten oder nur die MT4-Dateien aktualisiert werden sollen
 */
function processSignal($alias, $fileSyncOnly) {
   if (!is_string($alias))      throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));
   if (!is_bool($fileSyncOnly)) throw new IllegalTypeException('Illegal type of parameter $fileSyncOnly: '.getType($fileSyncOnly));


   // Ist ein Wildcard angegeben, wird die Funktion rekursiv für alle Signale aufgerufen.
   if ($alias == '*') {
      $self    = __FUNCTION__;
      foreach (Signal ::dao()->listAll() as $signal)
         $self($signal->getAlias(), $fileSyncOnly);
      return;
   }


   $signal = Signal ::dao()->getByAlias($alias);
   global $signalNamePadding;
   echo(str_pad($signal->getName().' ', $signalNamePadding, '.', STR_PAD_RIGHT).' ');

   $updates = false;                         // ob beim Synchronisieren Änderungen festgestellt wurden

   if (!$fileSyncOnly) {
      // HTML-Seite laden
      $content = SimpleTrader ::loadSignalPage($signal);

      // HTML-Seite parsen
      $openPositions = $closedPositions = array();
      SimpleTrader ::parseSignalData($signal, $content, $openPositions, $closedPositions);

      // Datenbank aktualisieren
     $updates = updateDatabase($signal, $openPositions, $closedPositions);
   }

   // Datenbasis für MT4 aktualisieren
   MT4 ::updateDataFiles($signal, $updates);
}


/**
 * Aktualisiert die lokalen offenen und geschlossenen Positionen. Partielle Closes lassen sich nicht vollständig erkennen
 * und werden daher wie reguläre Positionen behandelt und gespeichert.
 *
 * @param  Signal $signal               - Signal
 * @param  array  $currentOpenPositions - Array mit aktuell offenen Positionen
 * @param  array  $currentHistory       - Array mit aktuellen Historydaten
 *
 * @return bool - ob Änderungen detektiert wurden oder nicht
 */
function updateDatabase(Signal $signal, array &$currentOpenPositions, array &$currentHistory) {
   $unchangedOpenPositions   = 0;
   $positionChangeStartTimes = null;               // Beginn der Änderungen der Net-Position
   $lastKnownChangeTimes     = null;
   $modifications            = null;

   $db = OpenPosition ::dao()->getDB();
   $db->begin();
   try {
      // (1) lokalen Stand der offenen Positionen holen
      $knownOpenPositions = OpenPosition ::dao()->listBySignal($signal, $assocTicket=true);


      // (2) offene Positionen (sortiert nach OpenTime+Ticket) abgleichen
      foreach ($currentOpenPositions as $i => &$data) {
         $sTicket  = (string)$data['ticket'];

         if (!isSet($knownOpenPositions[$sTicket])) {
            if (!isSet($positionChangeStartTimes[$data['symbol']]))
               $lastKnownChangeTimes[$data['symbol']] = Signal ::dao()->getLastKnownPositionChangeTime($signal, $data['symbol']);

            $position = OpenPosition ::create($signal, $data)->save();
            $symbol   = $position->getSymbol();
            $openTime = $position->getOpenTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($openTime, $positionChangeStartTimes[$symbol]) : $openTime;
         }
         else {
            // auf geänderte Limite prüfen
            $position = null;
            if ($data['takeprofit'] != ($prevTP=$knownOpenPositions[$sTicket]->getTakeProfit())) $position = $knownOpenPositions[$sTicket]->setTakeProfit($data['takeprofit']);
            if ($data['stoploss'  ] != ($prevSL=$knownOpenPositions[$sTicket]->getStopLoss())  ) $position = $knownOpenPositions[$sTicket]->setStopLoss  ($data['stoploss'  ]);
            if ($position) {
               $modifications[$position->save()->getSymbol()][] = array('position' => $position,
                                                                        'prevTP'   => $prevTP,
                                                                        'prevSL'   => $prevSL);
               echoPre(MyFX ::fxtDate().' modified position');
            }
            else $unchangedOpenPositions++;
            unset($knownOpenPositions[$sTicket]);              // geprüfte Position aus Liste löschen
         }
      }


      // (3) History abgleichen (ist sortiert nach CloseTime+OpenTime+Ticket)
      $formerOpenPositions = $knownOpenPositions;              // alle in $knownOpenPositions übrig gebliebenen Positionen müssen geschlossen worden sein
      $hstSize             = sizeOf($currentHistory);
      $matchingPositions   = $otherClosedPositions = 0;
      $openGotClosed       = false;

      for ($i=$hstSize-1; $i >= 0; $i--) {                     // Die aufsteigende History wird rückwärts verarbeitet (schnellste Variante).
         $data         = $currentHistory[$i];
         $ticket       = $data['ticket'];
         $openPosition = null;

         if ($formerOpenPositions) {
            $sTicket = (string) $ticket;
            if (isSet($formerOpenPositions[$sTicket])) {
               $openPosition = OpenPosition ::dao()->getByTicket($signal, $ticket);
               unset($formerOpenPositions[$sTicket]);
            }
         }

         if (!$openPosition && ClosedPosition ::dao()->isTicket($signal, $ticket)) {
            $matchingPositions++;
            if ($matchingPositions >= 3)                       // Nach Übereinstimmung von 3 Datensätzen wird abgebrochen.
               break;
            continue;
         }

         if (!isSet($positionChangeStartTimes[$data['symbol']]))
            $lastKnownChangeTimes[$data['symbol']] = Signal ::dao()->getLastKnownPositionChangeTime($signal, $data['symbol']);

         // Position in t_closedposition einfügen
         if ($openPosition) {
            $closedPosition = ClosedPosition ::create($openPosition, $data)->save();
            $symbol         = $closedPosition->getSymbol();
            $closeTime      = $closedPosition->getCloseTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($closeTime, $positionChangeStartTimes[$symbol]) : $closeTime;
            $openPosition->delete();                           // vormals offene Position aus t_openposition löschen
            $openGotClosed = true;
         }
         else {
            $closedPosition = ClosedPosition ::create($signal, $data)->save();
            $symbol         = $closedPosition->getSymbol();
            $closeTime      = $closedPosition->getCloseTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($closeTime, $positionChangeStartTimes[$symbol]) : $closeTime;
            $otherClosedPositions++;
         }
      }


      // (4) ohne Änderungen
      if (!$positionChangeStartTimes && !$modifications) {
         echoPre('no changes'.($unchangedOpenPositions ? ' ('.$unchangedOpenPositions.' open position'.($unchangedOpenPositions==1 ? '':'s').')':''));
      }


      // (5) Formatierter und sortierter Report der Änderungen
      if ($positionChangeStartTimes) {
         global $signalNamePadding;
         $n = 0;

         foreach ($positionChangeStartTimes as $symbol => $startTime) {
            $n++;
            if ($startTime < $lastKnownChangeTimes[$symbol])
               $startTime = MyFX ::fxtDate(MyFX ::fxtStrToTime($lastKnownChangeTimes[$symbol]) + 1);

            $report = ReportHelper ::getNetPositionHistory($signal, $symbol, $startTime);
            $oldNetPosition     = 'Flat';
            $oldNetPositionDone = false;
            $iFirstNewRow       = null;

            foreach ($report as $i => $row) {
               if      ($row['total' ] > 0) $netPosition  = 'Long   '.number_format( $row['total'], 2);
               else if ($row['total' ] < 0) $netPosition  = 'Short  '.number_format(-$row['total'], 2);
               else if ($row['hedged'])     $netPosition  = 'Hedged '.str_repeat(' ', strLen(number_format(abs($report[$i-1]['total']), 2)));

               if      ($row['hedged'])     $netPosition .= ' +-'.number_format($row['hedged'], 2).' lots';
               else if ($row['total' ])     $netPosition .= ' lots';
               else                         $netPosition  = '-';

               if ($row['time'] >= $startTime) {
                  if (!$oldNetPositionDone) {
                     echoPre(($n==1 ? '':str_pad("\n", $signalNamePadding+2)).'                          was:              '.$oldNetPosition);
                     $oldNetPositionDone = true;
                     $iFirstNewRow       = $i;
                  }
                  echoPre($row['time'].':  '.str_pad($row['trade'], 5).' '. str_pad(ucFirst($row['type']), 4).' '.number_format($row['lots'], 2).' lots '.$row['symbol'].' @ '.str_pad($row['price'], 8).' '.$netPosition);
               }
               else $oldNetPosition = $netPosition;
            }
            SimpleTrader ::onPositionChange($signal, $symbol, $report, $iFirstNewRow, $oldNetPosition, $netPosition);
         }
         if (isSet($modifications[$symbol])) {
            echoPre('modify');
            //SimpleTrader ::onPositionModify($position, $prevTP, $prevSL);
            unset($modifications[$symbol]);
         }
      }

      if ($modifications) {
         echoPre('modify');
         //SimpleTrader ::onPositionModify($position, $prevTP, $prevSL);
      }


      if ($formerOpenPositions) throw new plRuntimeException('Found '.sizeOf($formerOpenPositions).' orphaned open position'.(sizeOf($formerOpenPositions)==1 ? '':'s').":\n".printFormatted($formerOpenPositions, true));

      // (6) alles speichern
      $db->commit();
   }
   catch (Exception $ex) {
      $db->rollback();
      throw $ex;
   }

   return ($positionChangeStartTimes || $modifications);
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
