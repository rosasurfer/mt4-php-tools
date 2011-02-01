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
      // Account holen
      $account = Account ::dao()->getByCompanyAndNumber($form->getAccountCompany(), $form->getAccountNumber());
      if (!$account) throw new InvalidArgumentException('unknown account');

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

         // Swaps und Vendor Matchings korrigieren
         if ($row[AH_TYPE] == OP_BALANCE) {
            if (String ::startsWith($row[AH_COMMENT], 'swap', true)) {
               if ($row[AH_SWAP] == 0) {
                  $row[AH_SWAP]   = $row[AH_PROFIT];
                  $row[AH_PROFIT] = 0;
               }
               $row[AH_TYPE] = OP_VENDORMATCHING;
            }
            else if (String ::startsWith($row[AH_COMMENT], 'vendor matching', true)) {
               $row[AH_TYPE] = OP_VENDORMATCHING;
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
            $ticket = (int) subStr($row[AH_COMMENT], 16);
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

      // (1.3) Transaktionen für SQL-Import formatieren und in die temporäre Datei zurückschreiben
      $fileName = $form->getFileTmpName();
      $fileName = 'E:/Projekte/fx.web/etc/tmp/tmp_accounthistory.sql.txt';
      $hFile = fOpen($fileName, 'wb');
      foreach ($transactions as &$row) {
         if ($row[AH_OPENTIME] == 0)
            continue;
         $row[AH_TYPE     ] = strToLower(ViewHelper ::$operationTypes[$row[AH_TYPE]]);
         $row[AH_OPENTIME ] = gmDate('Y-m-d H:i:s', $row[AH_OPENTIME ]);
         $row[AH_CLOSETIME] = gmDate('Y-m-d H:i:s', $row[AH_CLOSETIME]);
         fWrite($hFile, join("\t", $row)."\n");
      }
      fClose($hFile);

      // (1.4) Transaktionen importieren
      if (WINDOWS)
         $fileName = str_replace('\\', '/', $fileName);





      // (1.4) neue AccountBalance überprüfen



      /*
      echoPre("\n\ncorrected transactions:");
      foreach ($transactions as $i => $row) {
         echoPre(str_pad($row[AH_TICKET     ], 12).
                 str_pad($row[AH_OPENTIME   ], 12).
                 str_pad($row[AH_TYPE       ], 12).
                 str_pad($row[AH_UNITS      ], 12).
                 str_pad($row[AH_SYMBOL     ], 12).
                 str_pad($row[AH_OPENPRICE  ], 12).
                 str_pad($row[AH_CLOSETIME  ], 12).
                 str_pad($row[AH_CLOSEPRICE ], 12).
                 str_pad($row[AH_COMMISSION ], 12).
                 str_pad($row[AH_SWAP       ], 12).
                 str_pad($row[AH_PROFIT     ], 12).
                 str_pad($row[AH_MAGICNUMBER], 12).
                 str_pad($row[AH_COMMENT    ], 12));
      }
      */

      // (2.1) Credits sortieren
      // (2.1) Daten importieren
      // (2.2) Logische Validierung (Units > 0, OpenTime < CloseTime) in DB-Trigger

      /*
      $fileName = $form->getFileTmpName();
      if (WINDOWS)
         $fileName = str_replace('\\', '/', $fileName);

      $db = Invoice ::dao()->getDB();

      // Rohdaten in temporäre Tabelle laden
      $sql = "create temporary table t_tmp_raw (
                 docid       varchar(10) not null,
                 paymentdate date not null
              )";
      $db->executeSql($sql);

      $sql = "load data local infile '$fileName'
                 into table t_tmp_raw
                 fields
                    terminated by '\\t'
                 lines
                    terminated by '\\n'
                 (docid, paymentdate)";
      $db->executeSql($sql);

      // Dubletten entfernen
      $sql = "create temporary table t_tmp (
                 docid       varchar(10) not null,
                 paymentdate date not null,
                 invoice_id  int unsigned,

                 unique index u_docid (docid),
                 index i_paymentdate  (paymentdate)
              )
              select distinct docid,
                              paymentdate,
                              null as 'invoice_id'
                 from t_tmp_raw";
      $db->executeSql($sql);

      // invoice_id ermitteln und abspeichern
      $sql = "update t_tmp      t
                 join t_invoice i on i.docid = t.docid
                 set t.invoice_id = i.id";
      $db->executeSql($sql);


      $db->begin();
      try {
         // letztes Buchungsimportdatum ermitteln
         $sql = "set @lastImport = (select max(p.date)
                                       from t_payment p
                                       where p.account = 'olta')";
         $db->executeSql($sql);

         // Rechnungen aktualisieren
         $sql = "update t_invoice i
                    join t_tmp    t on i.id = t.invoice_id
                    set i.temporarypayment = t.paymentdate
                    where t.paymentdate > @lastImport             -- nur Daten speichern, die nach dem letzten Buchungsimport gemeldet wurden
                      and i.encashment is not null
                      and (i.temporarypayment is null or i.temporarypayment != t.paymentdate)";
         $result = $db->executeSql($sql);

         // alles speichern
         $db->commit();

         return $result['rows'];
      }
      catch (Exception $ex) {
         $db->rollback();
         throw new InfrastructureException($ex);
      }
      */
   }
}
?>
