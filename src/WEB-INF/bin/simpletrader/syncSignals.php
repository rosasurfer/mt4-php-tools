#!/usr/bin/php
<?php
/**
 * Synchronisiert die Daten ein oder mehrerer Signale mit den lokal gespeicherten Daten (Datenbank und MT4-Datenfiles).
 * Bei Datenänderung wird eine Mail und eine SMS verschickt.
 *
 *
 *
 */
require(dirName(realPath(__FILE__)).'/../../config.php');


$sleepSeconds      = 30;         // Länge der Pause zwischen zwei Updates
$signalNamePadding = 16;         // Padding der Anzeige des Signalnamens:  @see function processSignal()


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);


// (1.1) Optionen
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   if (in_array($arg, array('-?','-h','/h','-help','/help')))  exit(1|help());                  // Hilfe
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
$args = $args ? array_unique($args) : array('*');           // ohne Signal-Parameter werden alle Signale synchronisiert


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
      if (!processSignal($arg, $fileSyncOnly))
         exit(1);
   }
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
 *
 * @return bool - Erfolgsstatus
 */
function processSignal($alias, $fileSyncOnly) {
   if (!is_string($alias))      throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));
   if (!is_bool($fileSyncOnly)) throw new IllegalTypeException('Illegal type of parameter $fileSyncOnly: '.getType($fileSyncOnly));


   // Ist ein Wildcard angegeben, wird die Funktion rekursiv für alle Signale aufgerufen.
   if ($alias == '*') {
      $self = __FUNCTION__;
      foreach (Signal ::dao()->listAll() as $signal)
         $self($signal->getAlias(), $fileSyncOnly);
      return true;
   }

   static $openUpdates=false, $closedUpdates=false;                  // ob beim letzten Aufruf Änderungen eines Signals festgestellt wurden

   $signal = Signal ::dao()->getByAlias($alias);
   if (!$signal) {
      echoPre('Invalid or unknown signal: '.$alias);
      return false;
   }
   global $signalNamePadding;
   echo(($openUpdates ? "\n":'').str_pad($signal->getName().' ', $signalNamePadding, '.', STR_PAD_RIGHT).' ');


   if (!$fileSyncOnly) {
      $counter = 0;

      while (true) {
         $counter++;

         // HTML-Seite laden
         $html = SimpleTrader ::loadSignalPage($signal);

         // HTML-Seite parsen
         $openPositions = $closedPositions = array();
         $errorMsg      = SimpleTrader ::parseSignalData($signal, $html, $openPositions, $closedPositions);
         if (!$errorMsg)
            break;

         // bei PHP-Fehlern im generiertem HTML Seite nochmal laden (bis zu 5 Versuche)
         echoPre($errorMsg);
         if ($counter >= 5) throw new plRuntimeException($signal->getName().': '.$errorMsg);
         Logger ::log($signal->getName().': '.$errorMsg."\nretrying...", L_WARN, __CLASS__);
      }

      // Datenbank aktualisieren
     updateDatabase($signal, $openPositions, $openUpdates, $closedPositions, $closedUpdates);
   }
   else {
     $openUpdates = $closedUpdates = false;
   }

   // Datenbasis für MT4 aktualisieren
   MT4 ::updateDataFiles($signal, $openUpdates, $closedUpdates);

   return true;
}


/**
 * Aktualisiert die lokalen offenen und geschlossenen Positionen. Partielle Closes lassen sich nicht vollständig erkennen
 * und werden daher wie reguläre Positionen behandelt und gespeichert.
 *
 * @param  Signal $signal               - Signal
 * @param  array  $currentOpenPositions - Array mit aktuell offenen Positionen
 * @param  bool  &$openUpdates          - Variable, die nach Rückkehr anzeigt, ob Änderungen an den offenen Positionen detektiert wurden oder nicht
 * @param  array  $currentHistory       - Array mit aktuellen Historydaten
 * @param  bool  &$closedUpdates        - Variable, die nach Rückkehr anzeigt, ob Änderungen an der Trade-History detektiert wurden oder nicht
 */
function updateDatabase(Signal $signal, array &$currentOpenPositions, &$openUpdates, array &$currentHistory, &$closedUpdates) {
   if (!is_bool($openUpdates))   throw new IllegalTypeException('Illegal type of parameter $openUpdates: '.getType($openUpdates));
   if (!is_bool($closedUpdates)) throw new IllegalTypeException('Illegal type of parameter $closedUpdates: '.getType($closedUpdates));

   $unchangedOpenPositions   = 0;
   $positionChangeStartTimes = null;                                 // Beginn der Änderungen der Net-Position
   $lastKnownChangeTimes     = null;
   $modifications            = null;

   $db = OpenPosition ::dao()->getDB();
   $db->begin();
   try {
      // (1) lokalen Stand der offenen Positionen holen
      $knownOpenPositions = OpenPosition ::dao()->listBySignal($signal, $assocTicket=true);


      // (2) offene Positionen abgleichen
      foreach ($currentOpenPositions as $i => &$data) {
         $sTicket  = (string)$data['ticket'];

         if (!isSet($knownOpenPositions[$sTicket])) {
            // (2.1) neue offene Position
            if (!isSet($positionChangeStartTimes[$data['symbol']]))
               $lastKnownChangeTimes[$data['symbol']] = Signal ::dao()->getLastKnownPositionChangeTime($signal, $data['symbol']);

            $position = OpenPosition ::create($signal, $data)->save();
            $symbol   = $position->getSymbol();
            $openTime = $position->getOpenTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($openTime, $positionChangeStartTimes[$symbol]) : $openTime;
         }
         else {
            // (2.2) bekannte offene Position auf geänderte Limite prüfen
            $position = null;
            if ($data['takeprofit'] != ($prevTP=$knownOpenPositions[$sTicket]->getTakeProfit())) $position = $knownOpenPositions[$sTicket]->setTakeProfit($data['takeprofit']);
            if ($data['stoploss'  ] != ($prevSL=$knownOpenPositions[$sTicket]->getStopLoss())  ) $position = $knownOpenPositions[$sTicket]->setStopLoss  ($data['stoploss'  ]);
            if ($position) {
               $modifications[$position->save()->getSymbol()][] = array('position' => $position,
                                                                        'prevTP'   => $prevTP,
                                                                        'prevSL'   => $prevSL);
            }
            else $unchangedOpenPositions++;
            unset($knownOpenPositions[$sTicket]);                    // bekannte offene Position aus Liste löschen
         }
      }


      // (3) History abgleichen ($currentHistory ist sortiert nach CloseTime+OpenTime+Ticket)
      $formerOpenPositions = $knownOpenPositions;                    // Alle in $knownOpenPositions übrig gebliebenen Positionen existierten nicht in $currentOpenPositions
      $hstSize             = sizeOf($currentHistory);                // und müssen daher geschlossen worden sein.
      $matchingPositions   = $otherClosedPositions = 0;
      $openGotClosed       = false;

      for ($i=$hstSize-1; $i >= 0; $i--) {                           // Die aufsteigende History wird rückwärts verarbeitet (schnellste Variante).
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
            if ($matchingPositions >= 3 && !$formerOpenPositions)    // Nach Übereinstimmung von 3 Datensätzen wird abgebrochen.
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
            $openPosition->delete();                                 // vormals offene Position aus t_openposition löschen
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


      // (5) bei Änderungen: formatierter und sortierter Report
      if ($positionChangeStartTimes) {
         global $signalNamePadding;
         $n = 0;

         // (5.1) Positionsänderungen
         foreach ($positionChangeStartTimes as $symbol => $startTime) {
            $n++;
            if ($startTime < $lastKnownChangeTimes[$symbol])
               $startTime = MyFX ::fxtDate(MyFX ::fxtStrToTime($lastKnownChangeTimes[$symbol]) + 1);

            $report = ReportHelper ::getNetPositionHistory($signal, $symbol, $startTime);
            $oldNetPosition     = 'Flat';
            $oldNetPositionDone = false;
            $iFirstNewRow       = 0;

            foreach ($report as $i => $row) {
               if      ($row['total' ] > 0) $netPosition  = 'Long   '.number_format( $row['total'], 2);
               else if ($row['total' ] < 0) $netPosition  = 'Short  '.number_format(-$row['total'], 2);
               else if ($row['hedged'])     $netPosition  = 'Hedged '.str_repeat(' ', strLen(number_format(abs($report[$i-1]['total']), 2)));

               if      ($row['hedged'])     $netPosition .= ' +-'.number_format($row['hedged'], 2).' lots';
               else if ($row['total' ])     $netPosition .= ' lots';
               else                         $netPosition  = 'Flat';

               if ($row['time'] >= $startTime) {
                  if (!$oldNetPositionDone) {
                     $iFirstNewRow       = $i;
                     if (sizeOf($report) == $iFirstNewRow+1) echoPre("\n");   // keine Anzeige von $oldNetPosition bei nur einem neuen Trade
                     else                                    echoPre(($n==1 ? '':str_pad("\n", $signalNamePadding+2)).'                                             was: '.$oldNetPosition);
                     $oldNetPositionDone = true;
                  }
                  echoPre(date('Y-m-d H:i:s', MyFX ::fxtStrToTime($row['time'])).':  '.str_pad($row['trade'], 6).' '. str_pad(ucFirst($row['type']), 4).' '.number_format($row['lots'], 2).' lots '.$row['symbol'].' @ '.str_pad($row['price'], 8).' now: '.$netPosition);
               }
               else $oldNetPosition = $netPosition;
            }
            SimpleTrader ::onPositionChange($signal, $symbol, $report, $iFirstNewRow, $oldNetPosition, $netPosition);
         }

         // (5.2) Limitänderungen des jeweiligen Symbols nach Postionsänderung anfügen
         if (isSet($modifications[$symbol])) {
            foreach ($modifications[$symbol] as $modification)
               SimpleTrader ::onPositionModify($modification['position'], $modification['prevTP'], $modification['prevSL']);
            unset($modifications[$symbol]);
         }
      }

      // (5.3) restliche Limitänderungen für Symbole ohne Postionsänderung
      if ($modifications) {
         !$positionChangeStartTimes && echoPre("\n");
         foreach ($modifications as $modsPerSymbol) {
            foreach ($modsPerSymbol as $modification) {
               SimpleTrader ::onPositionModify($modification['position'], $modification['prevTP'], $modification['prevSL']);
            }
         }
      }


      // (6) bekannte Fehler selbständig abfangen
      if ($formerOpenPositions) {
         if ($signal->getAlias() == 'asta') {
            if (isSet($formerOpenPositions[$ticket='2111537'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2114818'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2118556'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2118783'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2128883'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2131107'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2132505'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2138672'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2138858'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2139720'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2140271'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2140276'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2140277'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2140282'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2141148'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142140'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142359'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142458'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142541'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142685'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142695'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2142976'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2143042'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2143070'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2143073'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2145221'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2160211'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2160281'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2162670'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2177484'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2178261'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2178283'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2180998'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2181751'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2181759'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2181960'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2182579'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2182588'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2182636'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2182766'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2182859'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2183175'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2183181'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2183273'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2183600'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2183611'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2183878'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2193718'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2240240'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2245473'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2246685'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2247448'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2247671'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2248728'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2248785'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2248917'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2249193'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2249521'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2249522'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2249703'])) unset($formerOpenPositions[$ticket]);
            if (isSet($formerOpenPositions[$ticket='2250222'])) unset($formerOpenPositions[$ticket]);
         }
      }
      if ($formerOpenPositions) throw new plRuntimeException('Found '.sizeOf($formerOpenPositions).' former open position'.(sizeOf($formerOpenPositions)==1 ? '':'s')." now neither in \"openTrades\" nor in \"history\":\n".printFormatted($formerOpenPositions, true));


      // (7) alles speichern
      $db->commit();
   }
   catch (Exception $ex) {
      $db->rollback();
      throw $ex;
   }

   $openUpdates   = $positionChangeStartTimes || $modifications;
   $closedUpdates = $openGotClosed || $otherClosedPositions;
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
