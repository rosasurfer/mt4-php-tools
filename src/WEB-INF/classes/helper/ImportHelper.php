<?php
/**
 * ImportHelper
 */
class ImportHelper extends StaticClass {


   /**
    * Importiert die mit der ActionForm übergebenen Historydaten eines Accounts.
    *
    * @param  UploadAccountHistoryActionForm $form - ActionForm
    *
    * @return int - Anzahl der importierten Datensätze
    */
   public static function updateAccountHistory(UploadAccountHistoryActionForm $form) {
      // Account suchen
      $company = Account ::normalizeCompanyName($form->getAccountCompany());
      $account = Account ::dao()->getByCompanyAndNumber($company, $form->getAccountNumber());
      if (!$account) throw new InvalidArgumentException('unknown_account');

      // Transaktionen und Credits trennen
      $transactions = $credits = null;
      $data =& $form->getFileData();
      foreach ($data as &$row) {
         if      ($row[AH_TYPE]==OP_BUY || $row[AH_TYPE]==OP_SELL || $row[AH_TYPE]==OP_BALANCE) $transactions[] =& $row;
         else if ($row[AH_TYPE]==OP_CREDIT)                                                     $credits[]      =& $row;
      }

      // (1.1) Transaktionen sortieren (by CloseTime ASC, OpenTime ASC, Ticket ASC)
      foreach ($transactions as $i => &$row) {
         $closeTimes[$i] = $row[AH_CLOSETIME];
         $openTimes [$i] = $row[AH_OPENTIME];
         $tickets   [$i] = $row[AH_TICKET];
      }
      array_multisort($closeTimes, SORT_ASC, $openTimes, SORT_ASC, $tickets, SORT_ASC, $transactions);

      // (1.2) Transaktionen korrigieren
      foreach ($transactions as $i => &$row) {
         if ($row[AH_OPENTIME] == 0)                                 // markierte Orders ignorieren (werden verworfen)
            continue;

         // Swaps und Vendor-Matchings korrigieren
         if ($row[AH_TYPE] == OP_BALANCE) {
            if (String ::startsWith($row[AH_COMMENT], 'swap', true)) {
               if ($row[AH_SWAP] == 0) {
                  $row[AH_SWAP]   = $row[AH_PROFIT];
                  $row[AH_PROFIT] = 0;
               }
               $row[AH_TYPE] = OP_VENDOR;
            }
            else if (String ::startsWith($row[AH_COMMENT], 'vendor matching', true)) {
               $row[AH_TYPE] = OP_VENDOR;
            }
            else {
               $row[AH_TYPE] = OP_TRANSFER;
            }
            if ($row[AH_OPENTIME] != $row[AH_CLOSETIME]) throw new InvalidArgumentException('ticket #'.$row[AH_TICKET].' - illegal balance times: open = "'.gmDate('Y.m.d H:i:s', $row[AH_OPENTIME]).'", close = "'.gmDate('Y.m.d H:i:s', $row[AH_CLOSETIME]).'"');
            continue;
         }

         // Hedges korrigieren
         if ($row[AH_UNITS] == 0) {
            // TODO: Prüfen, wie sich OrderComment() bei partiellem Close und/oder custom comments verhält.
            if (!String ::startsWith($row[AH_COMMENT], 'close hedge by #', true)) throw new InvalidArgumentException('ticket #'.$row[AH_TICKET].' - unknown comment for assumed hedged position: "'.$row[AH_COMMENT].'"');

            // Gegenstück suchen und alle Orderdaten in der 1. Order speichern
            $ticket = (int) subStr($row[AH_COMMENT], 16);            // (int) schneidet ggf. auf die Ticket# folgende nicht-numerische Zeichen ab
            if ($ticket == 0)                                        throw new InvalidArgumentException('ticket #'.$row[AH_TICKET].' - unknown comment for assumed hedged position: "'.$row[AH_COMMENT].'"');
            if (($n=array_search($ticket, $tickets, true)) == false) throw new InvalidArgumentException('cannot find counterpart for hedged position #'.$row[AH_TICKET].': "'.$row[AH_COMMENT].'"');

            $first  = min($i, $n);
            $second = max($i, $n);

            if ($i == $first) {
               $transactions[$first][AH_UNITS     ] = $transactions[$second][AH_UNITS     ];
               $transactions[$first][AH_CLOSEPRICE] = $transactions[$second][AH_OPENPRICE ];
               $transactions[$first][AH_COMMISSION] = $transactions[$second][AH_COMMISSION];
               $transactions[$first][AH_SWAP      ] = $transactions[$second][AH_SWAP      ];
               $transactions[$first][AH_PROFIT    ] = $transactions[$second][AH_PROFIT    ];
            }
            $transactions[$first ][AH_CLOSETIME] = $transactions[$second][AH_OPENTIME];
            $transactions[$first ][AH_COMMENT  ] = (strToLower($transactions[$first][AH_COMMENT])=='partial close' || strToLower($transactions[$second][AH_COMMENT])=='partial close' ? 'partial ':'').'close by hedge #'.$transactions[$second][AH_TICKET];
            $transactions[$second][AH_OPENTIME ] = 0;                // erste Order enthält jetzt alle Daten, hedgende Order markieren (und später verwerfen)
            $transactions[$second][AH_COMMENT  ] = ''; // temporär
            if ($i == $second)
               continue;
         }
         if ($row[AH_OPENTIME] >= $row[AH_CLOSETIME]) throw new InvalidArgumentException('ticket #'.$row[AH_TICKET].' - illegal order times: open = "'.gmDate('Y.m.d H:i:s', $row[AH_OPENTIME]).'", close = "'.gmDate('Y.m.d H:i:s', $row[AH_CLOSETIME]).'"');
      }

      // (1.3) Transaktionen für SQL-Import formatieren und in die hochgeladene Datei zurückschreiben
      $accountId       = $account->getId();
      $accountTimezone = new DateTimeZone($account->getTimezone());
      $newYorkTimezone = new DateTimeZone('America/New_York');

      $fileName = $form->getFileTmpName();
      $hFile = fOpen($fileName, 'wb');

      foreach ($transactions as &$row) {
         if ($row[AH_OPENTIME] == 0)
            continue;
         $row[AH_TYPE] = strToLower(ViewHelper ::$operationTypes[$row[AH_TYPE]]);

         // MT4-Serverzeiten in Forex-Standardzeit (America/New_York+0700) umrechnen
         foreach (array(AH_OPENTIME, AH_CLOSETIME) as $time) {
            $date = new DateTime(gmDate('Y-m-d H:i:s', $row[$time]), $accountTimezone);
            $date->setTimezone($newYorkTimezone);
            $date->modify('+7 hours');
            $row[$time] = $date->format('Y-m-d H:i:s');
         }
         fWrite($hFile, $accountId."\t".join("\t", $row)."\n");
      }
      fClose($hFile);

      // (1.4) Transaktionen importieren
      $db = Account ::dao()->getDB();

      // Rohdaten in temporäre Tabelle laden
      $sql = "create temporary table t_tmp (
                 account_id  int           unsigned  not null,
                 ticket      varchar(50)             not null,
                 opentime    datetime                not null,
                 type        varchar(255)            not null,
                 units       int           unsigned  not null,
                 symbol      varchar(255),
                 openprice   decimal(10,5) unsigned,
                 closetime   datetime                not null,
                 closeprice  decimal(10,5) unsigned,
                 commission  decimal(10,2)           not null,
                 swap        decimal(10,2)           not null,
                 netprofit   decimal(10,2)           not null,
                 magicnumber int           unsigned,
                 comment     varchar(255)            not null,
                 unique index u_account_id_ticket (account_id, ticket)
              )";
      $db->executeSql($sql);

      if (WINDOWS)
         $fileName = str_replace('\\', '/', $fileName);

      $sql = "load data local infile '$fileName'
                 into table t_tmp
                 fields
                    terminated by '\\t'
                 lines
                    terminated by '\\n'
                 (account_id, ticket, opentime, type, units, symbol, openprice, closetime, closeprice, commission, swap, netprofit, magicnumber, comment)";
      $db->executeSql($sql);

      // Tickets einfügen und bereits vorhandene ignorieren (die Trigger validieren strikter als IGNORE es ermöglicht)
      $db->begin();
      try {
         $sql = "insert ignore into t_transaction (ticket, type, units, symbol, opentime, openprice, closetime, closeprice, commission, swap, netprofit, magicnumber, comment, account_id)
                 select ticket                 as 'ticket',
                        type                   as 'type',
                        units                  as 'units',
                        nullIf(symbol, '')     as 'symbol',
                        opentime               as 'opentime',
                        nullIf(openprice, 0)   as 'openprice',
                        closetime              as 'closetime',
                        nullIf(closeprice, 0)  as 'closeprice',
                        commission             as 'commission',
                        swap                   as 'swap',
                        netprofit              as 'netprofit',
                        nullIf(magicnumber, 0) as 'magicnumber',
                        comment                as 'comment',
                        account_id             as 'account_id'
                    from t_tmp";
         $result = $db->executeSql($sql);

         // (1.5) neue AccountBalance gegenprüfen und speichern
         $reportedBalance = $form->getAccountBalance();
         if ($result['rows'] > 0)
            $account = Account ::dao()->refresh($account);
         if ($account->getBalance() != $reportedBalance) throw new BusinessRuleException('balance_mismatch');

         $account->setLastReportedBalance($reportedBalance)
                 ->save();

         // alles speichern
         $db->commit();
      }
      catch (BusinessRuleException $ex) {
         $db->rollback();
         throw $ex;
      }
      catch (Exception $ex) {
         $db->rollback();
         throw new InfrastructureException($ex);
      }


      // (2.1) Credits sortieren
      // (2.1) Daten importieren
      // (2.2) Logische Validierung (Units > 0, OpenTime < CloseTime) in DB-Trigger
      return $result['rows'];
   }
}
?>
