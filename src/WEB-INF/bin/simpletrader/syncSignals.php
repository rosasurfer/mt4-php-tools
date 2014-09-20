#!/usr/bin/php -Cq
<?
/**
 * Synchronisiert die Daten ein oder mehrerer Signale mit den lokal gespeicherten Daten.  Die lokalen Daten können
 * sich in einer Datenbank oder in einer Textdatei befinden. Bei Datenänderung kann ein MT4-Terminal benachrichtigt
 * und eine Mail oder SMS verschickt werden.
 */
require(dirName(realPath(__FILE__)).'/../config.php');


// zur Zeit unterstützte Signale
$signals = array('alexprofit'   => array('id'   => 2474,
                                         'name' => 'AlexProfit',
                                       //'url'  => 'http://cp.forexsignals.com/signal/2474/signal.html'),   // ohne SSL: komprimiert
                                         'url'  => 'https://www.simpletrader.net/signal/2474/signal.html'), //  mit SSL: nicht komprimiert

                 'dayfox'       => array('id'   => 2465,
                                         'name' => 'DayFox',
                                         'url'  => 'http://cp.forexsignals.com/signal/2465/signal.html'),

                 'goldstar'     => array('id'   => 2622,
                                         'name' => 'GoldStar',
                                         'url'  => 'http://cp.forexsignals.com/signal/2622/signal.html'),

                 'smarttrader'  => array('id'   => 1081,
                                         'name' => 'SmartTrader',
                                         'url'  => 'http://cp.forexsignals.com/signal/1081/signal.html'),

                 'smartscalper' => array('id'   => 1086,
                                         'name' => 'SmartScalper',
                                         'url'  => 'http://cp.forexsignals.com/signal/1086/signal.html'),
                 );


// --- Start --------------------------------------------------------------------------------------------------------------------------------------------------


// Befehlszeilenargumente einlesen und validieren
$args = array_slice($_SERVER['argv'], 1);
!$args && $args=array_keys($signals);                                // ohne Parameter werden alle Signale synchronisiert

foreach ($args as $i => $arg) {
   $arg = strToLower($arg);
   in_array($arg, array('-?','/?','-h','/h','-help','/help')) && exit(1|help());
   !array_key_exists($arg, $signals)                          && exit(1|help('Unknown signal: '.$args[$i]));
   $args[$i] = $arg;
}
$args = array_unique($args);


// Prüfen, ob die Datenbank erreichbar ist
try {
   Signal ::dao()->getDB()->executeSql("select 1 from dual");
}
catch (Exception $ex) {
   if ($ex instanceof InfrastructureException)
      exit(1|echoPre('error: '.$ex->getMessage()));                  // Can not connect to MySQL server on 'localhost:3306'
   throw $ex;
}


// Signale verarbeiten
foreach ($args as $i => $arg) {
   processSignal($arg);
}
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

   global $signals;
   $signalID   = $signals[$signal]['id'  ];
   $signalName = $signals[$signal]['name'];
   $signalUrl  = $signals[$signal]['url' ];

   echoPre("\nSyncing signal $signalName...");

   /**
    * URL:    http://cp.forexsignals.com/signal/{signal_id}/signal.html                               (mit und ohne SSL)
    * Cookie: email=address@domain.tld; session=***REMOVED***               (ohne SSL komprimiert)
    *
    * URL:    https://www.simpletrader.net/signal/{signal_id}/signal.html                             (nur mit SSL)
    * Cookie: email=address@domain.tld; session=***REMOVED***    (nicht komprimiert)
    */

   // GET /signal/2465/signal.html HTTP/1.1
   // Host:            cp.forexsignals.com
   // User-Agent:      ***REMOVED***
   // Accept:          text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
   // Accept-Language: en-us
   // Accept-Charset:  ISO-8859-1,utf-8;q=0.7,*;q=0.7
   // Accept-Encoding: gzip, deflate
   // Keep-Alive:      115
   // Connection:      keep-alive
   // Referer:         http://cp.forexsignals.com/forex-signals.html
   // Cookie:          email=address@domain.tld; session=***REMOVED***

   // HTTP-Request definieren und Browser simulieren
   $request = HttpRequest ::create()
                          ->setUrl($signalUrl)
                          ->setHeader('User-Agent'     , '***REMOVED***')
                          ->setHeader('Accept'         , 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
                          ->setHeader('Accept-Language', 'en-us')
                          ->setHeader('Accept-Charset' , 'ISO-8859-1,utf-8;q=0.7,*;q=0.7')
                          ->setHeader('Keep-Alive'     , '115')
                          ->setHeader('Connection'     , 'keep-alive');

   // Cookies in der angegebenen Datei verwenden/speichern
   $cookieStore = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.'cookies.txt';
   $options = array(CURLOPT_COOKIEFILE => $cookieStore,     // The name of a file containing cookie data to use for the request.
                    CURLOPT_COOKIEJAR  => $cookieStore);    // The name of a file to save cookie data to when the connection closes.

   // HTTP-Request ausführen
   if (true) {
      $options[CURLOPT_SSL_VERIFYPEER] = false;             // das SSL-Zertifikat von www.simpletrader.net ist u.U. ungültig

      $response = CurlHttpClient ::create($options)->send($request);
      $status   = $response->getStatus();
      $content  = $response->getContent();
      if ($status != 200) throw new plRuntimeException('Unexpected HTTP status code from cp.forexsignals.com: '.$status.' ('.HttpResponse ::$sc[$status].')');
   }
   else {
      $filename = dirName(realPath($_SERVER['PHP_SELF'])).DIRECTORY_SEPARATOR.$signal.'.html';
      $content  = file_get_contents($filename, false);
   }

   // Antwort parsen
   $openPositions = $closedPositions = array();
   SimpleTrader ::parseSignalData($signal, $content, $openPositions, $closedPositions);

   // lokale Daten aktualisieren
   updateTrades($signal, $openPositions, $closedPositions);
}


/**
 * Aktualisiert die offenen und geschlossenen Positionen.
 *
 * @param  string $signal               - Signal
 * @param  array  $currentOpenPositions - Array mit aktuell offenen Positionen
 * @param  array  $currentHistory       - Array mit aktuellen Historydaten
 */
function updateTrades($signal, array &$currentOpenPositions, array &$currentHistory) {
   $updates            = false;
   $unchangedPositions = 0;

   // (1) letzten bekannten Stand der offenen Positionen holen
   $knownOpenPositions = OpenPosition ::dao()->listBySignalAlias($signal, $assocTicket=true);


   // (2) offene Positionen abgleichen (sind aufsteigend nach OpenTime+Ticket sortiert)
   foreach ($currentOpenPositions as $i => &$data) {
      $sTicket  = (string)$data['ticket'];
      $position = null;

      if (!isSet($knownOpenPositions[$sTicket])) {
         MyFX ::onPositionOpen(OpenPosition ::create($signal, $data)->save());
         $updates = true;
      }
      else {
         // auf modifiziertes TP- oder SL-Limit prüfen
         if ($data['takeprofit'] != $knownOpenPositions[$sTicket]->getTakeProfit()) $position = $knownOpenPositions[$sTicket]->setTakeProfit($data['takeprofit']);
         if ($data['stoploss'  ] != $knownOpenPositions[$sTicket]->getStopLoss()  ) $position = $knownOpenPositions[$sTicket]->setStopLoss  ($data['stoploss'  ]);
         if ($position) {
            MyFX ::onPositionModify($position->save());
            $updates = true;
         }
         else $unchangedPositions++;
         unset($knownOpenPositions[$sTicket]);              // geprüfte Position aus Liste löschen
      }
   }
   $unchangedPositions && echoPre($unchangedPositions.' unchanged open position'.($unchangedPositions==1 ? '':'s'));


   // (3) History abgleichen (ist aufsteigend nach CloseTime+OpenTime+Ticket sortiert)
   $closedPositions   = $knownOpenPositions;                // alle in $knownOpenPositions übrig gebliebenen Positionen müssen geschlossen worden sein
   $hstSize           = sizeOf($currentHistory);
   $matchingPositions = $otherPositions = 0;                // nach 3 übereinstimmenden Historyeinträgen wird das Update abgebrochen
   $openGotClosed     = false;

   for ($i=$hstSize-1; $i >= 0; $i--) {                     // History wird rückwärts verarbeitet und bricht bei Übereinstimmung der Daten ab (schnellste Variante)
      $data         = $currentHistory[$i];
      $ticket       = $data['ticket'];
      $openPosition = null;

      if ($closedPositions) {
         $sTicket = (string) $ticket;
         if (isSet($closedPositions[$sTicket])) {
            $openPosition = OpenPosition ::dao()->getByTicket($signal, $ticket);
            unset($closedPositions[$sTicket]);
            $updates = true;
         }
      }

      if (!$openPosition && ClosedPosition ::dao()->isTicket($signal, $ticket)) {
         $matchingPositions++;
         if ($matchingPositions >= 3)
            break;
         continue;
      }

      // Position in t_closedposition einfügen
      if ($openPosition) {
         $closedPosition = ClosedPosition ::create($openPosition, $data)->save();
         $openPosition->delete();                           // vormals offene Position aus t_openposition löschen
         MyFX ::onPositionClose($closedPosition);
         $openGotClosed = true;
      }
      else {
         ClosedPosition ::create($signal, $data)->save();
         $otherPositions++;
      }
      $updates = true;
   }
   $otherPositions && echoPre($otherPositions.' '.(!$openGotClosed ? 'new':'other').' closed position'.($otherPositions==1 ? '':'s'));
   !$updates && !$unchangedPositions && echoPre('no changes');

   if ($closedPositions) throw new plRuntimeException('Found '.sizeOf($closedPositions).' orphaned open position'.(sizeOf($closedPositions)==1 ? '':'s').":\n".printFormatted($closedPositions, true));
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
