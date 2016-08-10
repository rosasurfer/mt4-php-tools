#!/usr/bin/php
<?php
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InfrastructureException;
use rosasurfer\exception\RuntimeException;

use rosasurfer\myfx\lib\myfxbook\MyfxBook;


/**
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 */
require(__DIR__.'/../../app/init.php');


$signalNamePadding = 21;                              // configuration of output formatting


// --- program start ---------------------------------------------------------------------------------------------------


// (1) read command line arguments
$args = array_slice($_SERVER['argv'], 1);

// parse and normalize arguments
foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   in_array($arg, ['-h','--help']) && exit(1|help());
   $args[$i] = $arg;
}
$args = $args ? array_unique($args) : ['*'];          // without arguments all signals "*" are processed


// (2) check database connectivity                    // produces just a short error notice instead of a hard exception
try {                                                 // as it would happen at runtime
   Signal::dao()->getDB()->executeSql("select 1");
}
catch (InfrastructureException $ex) {
   strStartsWithI($ex->getMessage(), 'can not connect') && exit(1|echoPre($ex->getMessage()));
   throw $ex;
}


// (3) update all specified accounts
foreach ($args as $arg) {
   !processAccounts($arg) && exit(1);
}


// (4) regular program end
exit(0);


// --- function definitions --------------------------------------------------------------------------------------------


/**
 * Update the specified MyfxBook accounts.
 *
 * @param  string $alias - account alias or "*" (updates all active MyfxBook accounts)
 *
 * @return bool - success status
 */
function processAccounts($alias) {
   if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));

   // if the wildcard "*" is specified recursively process all active accounts
   if ($alias == '*') {
      $me = __FUNCTION__;
      foreach (Signal::dao()->listActiveMyfxBook() as $signal) {
         $me($signal->getAlias());
      }
      return true;
   }

   $signal = Signal::dao()->getByProviderAndAlias($provider='myfxbook', $alias);
   if (!$signal) return _false(echoPre('Invalid or unknown signal: "'.$provider.':'.$alias.'"'));

   global $signalNamePadding;                               // output formatting: whether or not the last function call
   static $openUpdates=false, $closedUpdates=false;         //                    detected open trade/history changes
   echo(($openUpdates ? NL:'').str_pad($signal->getName().' ', $signalNamePadding, '.', STR_PAD_RIGHT).' ');

   // load CSV statement
   $csv = MyfxBook::loadCsvStatement($signal);

   // parse statement
   $openPositions = $closedPositions = [];
   $errorMsg = MyfxBook::parseCsvStatement($signal, $csv, $openPositions, $closedPositions);
   if ($errorMsg) throw new RuntimeException($signal->getName().': '.$errorMsg);

   echoPre(sizeOf($closedPositions).' closed positions');
   return false;

   // update database
   if (!updateDatabase($signal, $openPositions, $openUpdates, $closedPositions, $closedUpdates))
      return false;

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

   $db = OpenPosition::dao()->getDB();
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

            $position = OpenPosition ::create($signal, $data)->save();
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


      // (5) ohne Änderungen
      if (!$positionChangeStartTimes && !$modifications) {
         echoPre('no changes'.($unchangedOpenPositions ? ' ('.$unchangedOpenPositions.' open position'.($unchangedOpenPositions==1 ? '':'s').')':''));
      }


      // (6) bei Änderungen: formatierter und sortierter Report
      if ($positionChangeStartTimes) {
         global $signalNamePadding;
         $n = 0;

         // (6.1) Positionsänderungen
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
                     $iFirstNewRow       = $i;                                         // keine Anzeige von $oldNetPosition bei nur einem
                     if (sizeOf($report) == $iFirstNewRow+1) echoPre("\n");            // neuen Trade
                     else                                    echoPre(($n==1 && !$fullHistory ? '' : str_pad("\n", $signalNamePadding+2, ' ', STR_PAD_RIGHT)).str_repeat(' ', $signalNamePadding+20).'was: '.$oldNetPosition);
                     $oldNetPositionDone = true;
                  }
                  $format = "%s:  %-6s %-4s %5.2F lots %s @ %-8s now: %s";
                  $date   = date('Y-m-d H:i:s', MyFX ::fxtStrToTime($row['time'  ]));
                  $deal   =                                         $row['trade' ];
                  $type   =                                 ucFirst($row['type'  ]);
                  $lots   =                                         $row['lots'  ];
                  $symbol =                                         $row['symbol'];    // Consolen-Output für "[open|close] position...",
                  $price  =                                         $row['price' ];    // "modify ..." in SimpleTrader::onPositionModify()
                  echoPre(sprintf($format, $date, $deal, $type, $lots, $symbol, $price, $netPosition));
               }
               else $oldNetPosition = $netPosition;
            }
            SimpleTrader ::onPositionChange($signal, $symbol, $report, $iFirstNewRow, $oldNetPosition, $netPosition);
         }

         // (6.2) Limitänderungen des jeweiligen Symbols nach Positionsänderung anfügen
         if (isSet($modifications[$symbol])) {
            foreach ($modifications[$symbol] as $modification)
               SimpleTrader ::onPositionModify($modification['position'], $modification['prevTP'], $modification['prevSL']);
            unset($modifications[$symbol]);
         }
      }

      // (6.3) restliche Limitänderungen für Symbole ohne Postionsänderung
      if ($modifications) {
         !$positionChangeStartTimes && echoPre("\n");
         foreach ($modifications as $modsPerSymbol) {
            foreach ($modsPerSymbol as $modification) {
               SimpleTrader ::onPositionModify($modification['position'], $modification['prevTP'], $modification['prevSL']);
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
            $errorMsg = 'Found '.sizeOf($formerOpenPositions).' former open position'.(sizeOf($formerOpenPositions)==1 ? '':'s')
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

 Syntax:  $self [-f] [signal_name ...]

 Options:  -f  Rewrites the MetaTrader account history files (does not go online).
           -h  This help screen.


END;
}
