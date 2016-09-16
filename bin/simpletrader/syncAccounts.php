#!/usr/bin/php
<?php
/**
 * Synchronisiert die Daten ein oder mehrerer Signale mit den lokal gespeicherten Daten (Datenbank und MT4-Datenfiles).
 * Bei Datenänderung kann eine Mail oder eine SMS verschickt werden.
 *
 *
 *
 */
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InfrastructureException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\myfx\lib\simpletrader\SimpleTrader;

require(__DIR__.'/../../app/init.php');


$sleepSeconds      = 30;         // Länge der Pause zwischen zwei Updates
$signalNamePadding = 21;         // Padding der Anzeige des Signalnamens:  @see function processSignal()


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);


// (1.1) Optionen parsen
$looping = $fileSyncOnly = false;
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   if (in_array($arg, ['-h','--help'])) { exit(1|help());                                 }     // Hilfe
   if (in_array($arg, ['-l']))          { $looping     =true; unset($args[$i]); continue; }     // -l=Looping
   if (in_array($arg, ['-f']))          { $fileSyncOnly=true; unset($args[$i]); continue; }     // -f=FileSyncOnly
}


// (1.2) Groß-/Kleinschreibung normalisieren
foreach ($args as $i => $arg) {
   $args[$i] = strToLower($arg);
}
$args = $args ? array_unique($args) : ['*'];                   // ohne Signal-Parameter werden alle Signale synchronisiert


// (2) Erreichbarkeit der Datenbank prüfen                     // Als Extra-Schritt, damit ein Connection-Fehler bei Programmstart nur eine
try {                                                          // kurze Fehlermeldung, während der Programmausführung jedoch einen kritischen
   Signal::dao()->getDB()->executeSql("select 1");             // Fehler (mit Stacktrace) auslöst.
}
catch (InfrastructureException $ex) {
   strStartsWithI($ex->getMessage(), 'can not connect') && exit(1|echoPre($ex->getMessage()));
   throw $ex;
}


// (3) Signale aktualisieren
while (true) {
   foreach ($args as $i => $arg) {
      !processSignal($arg, $fileSyncOnly) && exit(1);
   }
   if (!$looping) break;
   sleep($sleepSeconds);                                       // vorm nächsten Durchlauf jeweils einige Sek. schlafen
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

   // if the wildcard "*" is specified recursively process all active accounts
   if ($alias == '*') {
      $me = __FUNCTION__;
      foreach (Signal::dao()->listActiveSimpleTrader() as $signal)
         $me($signal->getAlias(), $fileSyncOnly);
      return true;
   }

   $signal = Signal::dao()->getByProviderAndAlias($provider='simpletrader', $alias);
   if (!$signal) return _false(echoPre('Invalid or unknown signal: "'.$provider.':'.$alias.'"'));

   global $signalNamePadding;                               // output formatting: whether or not the last function call
   static $openUpdates=false, $closedUpdates=false;         //                    detected open trade/history changes
   echo(($openUpdates ? NL:'').str_pad($signal->getName().' ', $signalNamePadding, '.', STR_PAD_RIGHT).' ');


   if (!$fileSyncOnly) {
      $counter     = 0;
      $fullHistory = false;

      while (true) {
         $counter++;

         // HTML-Seite laden
         $html = SimpleTrader::loadSignalPage($signal, $fullHistory);

         // HTML-Seite parsen
         $openPositions = $closedPositions = array();
         $errorMsg = SimpleTrader::parseSignalData($signal, $html, $openPositions, $closedPositions);

         // bei PHP-Fehlermessages in HTML-Seite URL nochmal laden (bis zu 5 Versuche)
         if ($errorMsg) {
            echoPre($errorMsg);
            if ($counter >= 5) throw new RuntimeException($signal->getName().': '.$errorMsg);
            Logger::warn($signal->getName().': '.$errorMsg."\nretrying...", __CLASS__);
            continue;
         }

         // Datenbank aktualisieren
         try {
            if (!updateDatabase($signal, $openPositions, $openUpdates, $closedPositions, $closedUpdates, $fullHistory))
               return false;
            break;
         }
         catch (DataNotFoundException $ex) {
            if ($fullHistory) throw $ex;              // Fehler weiterreichen, wenn er mit kompletter History auftrat

            // Zähler zurücksetzen und komplette History laden
            $counter     = 0;
            $fullHistory = true;
            echoPre($ex->getMessage().', loading full history...');
         }
      }
   }
   else {
     $openUpdates = $closedUpdates = false;
   }

   // update MQL account history files
   MT4::updateAccountHistory($signal, $openUpdates, $closedUpdates);

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
 * @param  bool   $fullHistory          - ob die komplette History geladen wurde (für korrektes Padding der Anzeige)
 *
 * @return bool - Erfolgsstatus
 *
 * @throws DataNotFoundException - wenn die älteste geschlossene Position lokal nicht vorhanden ist (auch beim ersten Synchronisieren)
 *                               - wenn eine beim letzten Synchronisieren offene Position weder als offen noch als geschlossen angezeigt wird
 */
function updateDatabase(Signal $signal, array &$currentOpenPositions, &$openUpdates, array &$currentHistory, &$closedUpdates, $fullHistory) {   // die zusätzlichen Zeiger minimieren den Speicherverbrauch
   if (!is_bool($openUpdates))   throw new IllegalTypeException('Illegal type of parameter $openUpdates: '.getType($openUpdates));
   if (!is_bool($closedUpdates)) throw new IllegalTypeException('Illegal type of parameter $closedUpdates: '.getType($closedUpdates));
   if (!is_bool($fullHistory))   throw new IllegalTypeException('Illegal type of parameter $fullHistory: '.getType($fullHistory));

   $unchangedOpenPositions   = 0;
   $positionChangeStartTimes = array();                              // Beginn der Änderungen der Net-Position
   $lastKnownChangeTimes     = array();
   $modifications            = array();

   $db = Signal::dao()->getDB();
   $db->begin();
   try {
      // (1) bei partieller History prüfen, ob die älteste geschlossene Position lokal vorhanden ist
      if (!$fullHistory) {
         foreach ($currentHistory as $data) {
            if (!$data) continue;                                    // Datensätze übersprungener Zeilen können leer sein.
            $ticket = $data['ticket'];
            if (!ClosedPosition::dao()->isTicket($signal, $ticket))
               throw new DataNotFoundException('Closed position #'.$ticket.' not found locally');
            break;
         }
      }


      // (2) lokalen Stand der offenen Positionen holen
      $knownOpenPositions = OpenPosition::dao()->listBySignal($signal, $assocTicket=true);


      // (3) offene Positionen abgleichen
      foreach ($currentOpenPositions as $i => $data) {
         if (!$data) continue;                                       // Datensätze übersprungener Zeilen können leer sein.
         $sTicket = (string)$data['ticket'];

         if (!isSet($knownOpenPositions[$sTicket])) {
            // (3.1) neue offene Position
            if (!isSet($positionChangeStartTimes[$data['symbol']]))
               $lastKnownChangeTimes[$data['symbol']] = Signal::dao()->getLastKnownPositionChangeTime($signal, $data['symbol']);

            $position = OpenPosition::create($signal, $data)->save();
            $symbol   = $position->getSymbol();
            $openTime = $position->getOpenTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($openTime, $positionChangeStartTimes[$symbol]) : $openTime;
         }
         else {
            // (3.2) bekannte offene Position auf geänderte Limite prüfen
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


      // (4) History abgleichen ($currentHistory ist sortiert nach CloseTime+OpenTime+Ticket)
      $formerOpenPositions = $knownOpenPositions;                    // Alle in $knownOpenPositions übrig gebliebenen Positionen existierten nicht
      $hstSize             = sizeOf($currentHistory);                // in $currentOpenPositions und müssen daher geschlossen worden sein.
      $matchingPositions   = $otherClosedPositions = 0;
      $openGotClosed       = false;

      for ($i=$hstSize-1; $i >= 0; $i--) {                           // Die aufsteigende History wird rückwärts verarbeitet (schnellste Variante).
         if (!$data=$currentHistory[$i])                             // Datensätze übersprungener Zeilen können leer sein.
            continue;
         $ticket       = $data['ticket'];
         $openPosition = null;

         if ($formerOpenPositions) {
            $sTicket = (string) $ticket;
            if (isSet($formerOpenPositions[$sTicket])) {
               $openPosition = OpenPosition::dao()->getByTicket($signal, $ticket);
               unset($formerOpenPositions[$sTicket]);
            }
         }

         if (!$openPosition && ClosedPosition::dao()->isTicket($signal, $ticket)) {
            $matchingPositions++;
            if ($matchingPositions >= 3 && !$formerOpenPositions)    // Nach Übereinstimmung von 3 Datensätzen wird abgebrochen.
               break;
            continue;
         }

         if (!isSet($positionChangeStartTimes[$data['symbol']]))
            $lastKnownChangeTimes[$data['symbol']] = Signal::dao()->getLastKnownPositionChangeTime($signal, $data['symbol']);

         // Position in t_closedposition einfügen
         if ($openPosition) {
            $closedPosition = ClosedPosition::create($openPosition, $data)->save();
            $symbol         = $closedPosition->getSymbol();
            $closeTime      = $closedPosition->getCloseTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($closeTime, $positionChangeStartTimes[$symbol]) : $closeTime;
            $openPosition->delete();                                 // vormals offene Position aus t_openposition löschen
            $openGotClosed = true;
         }
         else {
            $closedPosition = ClosedPosition::create($signal, $data)->save();
            $symbol         = $closedPosition->getSymbol();
            $closeTime      = $closedPosition->getCloseTime();
            $positionChangeStartTimes[$symbol] = isSet($positionChangeStartTimes[$symbol]) ? min($closeTime, $positionChangeStartTimes[$symbol]) : $closeTime;
            $otherClosedPositions++;
         }
      }


      // (5) ohne Änderungen
      if (!$positionChangeStartTimes && !$modifications) {
         echoPre('no changes'.($unchangedOpenPositions ? ' ('.$unchangedOpenPositions.' open position'.pluralize($unchangedOpenPositions).')':''));
      }


      // (6) bei Änderungen: formatierter und sortierter Report
      if ($positionChangeStartTimes) {
         global $signalNamePadding;
         $n = 0;

         // (6.1) Positionsänderungen
         foreach ($positionChangeStartTimes as $symbol => $startTime) {
            $n++;
            if ($startTime < $lastKnownChangeTimes[$symbol])
               $startTime = MyFX::fxtDate(MyFX::fxtStrToTime($lastKnownChangeTimes[$symbol]) + 1);

            $report = ReportHelper::getNetPositionHistory($signal, $symbol, $startTime);
            $oldNetPosition     = 'Flat';
            $oldNetPositionDone = false;
            $iFirstNewRow       = 0;

            foreach ($report as $i => $row) {
               if      ($row['total' ] > 0) $netPosition  = 'Long  '.number_format( $row['total'], 2);
               else if ($row['total' ] < 0) $netPosition  = 'Short '.number_format(-$row['total'], 2);
               else if ($row['hedged'])     $netPosition  = 'Hedge '.str_repeat(' ', strLen(number_format(abs($report[$i-1]['total']), 2)));

               if      ($row['hedged'])     $netPosition .= ' +-'.number_format($row['hedged'], 2).' lot';
               else if ($row['total' ])     $netPosition .= ' lot';
               else                         $netPosition  = 'Flat';

               if ($row['time'] >= $startTime) {
                  if (!$oldNetPositionDone) {
                     $iFirstNewRow       = $i;                                         // keine Anzeige von $oldNetPosition bei nur einem
                     if (sizeOf($report) == $iFirstNewRow+1) echoPre("\n");            // neuen Trade
                     else                                    echoPre(($n==1 && !$fullHistory ? '' : str_pad("\n", $signalNamePadding+2, ' ', STR_PAD_RIGHT)).str_repeat(' ', $signalNamePadding+14).'was: '.$oldNetPosition);
                     $oldNetPositionDone = true;
                  }
                  $format = "%s:  %-6s %-4s %4.2F %s @ %-8s now: %s";
                  $date   = date('Y-m-d H:i:s', MyFX::fxtStrToTime($row['time'  ]));
                  $deal   =          ($row['trade']=='open') ? '': $row['trade' ];     // "open" wird nicht extra angezeigt
                  $type   =                                ucFirst($row['type'  ]);
                  $lots   =                                        $row['lots'  ];
                  $symbol =                                        $row['symbol'];     // Consolen-Output für "[open|close] position...",
                  $price  =                                        $row['price' ];     // "modify ..." in SimpleTrader::onPositionModify()
                  echoPre(sprintf($format, $date, $deal, $type, $lots, $symbol, $price, $netPosition));
               }
               else $oldNetPosition = $netPosition;
            }
            SimpleTrader::onPositionChange($signal, $symbol, $report, $iFirstNewRow, $oldNetPosition, $netPosition);
         }

         // (6.2) Limitänderungen des jeweiligen Symbols nach Positionsänderung anfügen
         if (isSet($modifications[$symbol])) {
            foreach ($modifications[$symbol] as $modification)
               SimpleTrader::onPositionModify($modification['position'], $modification['prevTP'], $modification['prevSL']);
            unset($modifications[$symbol]);
         }
      }

      // (6.3) restliche Limitänderungen für Symbole ohne Postionsänderung
      if ($modifications) {
         !$positionChangeStartTimes && echoPre(NL);
         foreach ($modifications as $modsPerSymbol) {
            foreach ($modsPerSymbol as $modification) {
               SimpleTrader::onPositionModify($modification['position'], $modification['prevTP'], $modification['prevSL']);
            }
         }
      }


      // (7) nicht zuzuordnende Positionen: ggf. muß die komplette History geladen werden
      if ($formerOpenPositions) {
         $errorMsg = null;
         if (!$fullHistory) {
            $errorMsg = 'Close data not found for former open position #'.array_shift($formerOpenPositions)->getTicket();
         }
         else {
            $errorMsg = 'Found '.sizeOf($formerOpenPositions).' former open position'.pluralize(sizeOf($formerOpenPositions))
                      ." now neither in \"openTrades\" nor in \"history\":\n".printPretty($formerOpenPositions, true);
         }
         throw new DataNotFoundException($errorMsg);
      }


      // (8) alles speichern
      $db->commit();
   }
   catch (\Exception $ex) {
      $db->rollback();
      throw $ex;
   }

   $openUpdates   = $positionChangeStartTimes || $modifications;
   $closedUpdates = $openGotClosed || $otherClosedPositions;

   return true;
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

 Options:  -l  Runs infinitely and synchronizes every 30 seconds.
           -f  Synchronizes MetaTrader data files but not the database (does not go online).
           -h  This help screen.


END;
}
