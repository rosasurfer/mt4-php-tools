<?php
/**
 * UploadAccountHistoryActionForm
 *
 * Verarbeitet sowohl Uploads per HTML-Formular (multipart/form-data) als auch per MQL-Schnittstelle (text/plain).
 * Verarbeitet noch keine komprimierten Daten.
 */
class UploadAccountHistoryActionForm extends ActionForm {


   private /*mixed[]*/ $file = array('name'     => null,    // die hochgeladene Datei
                                     'type'     => null,
                                     'tmp_name' => null,
                                     'error'    => null,
                                     'size'     => null);
   private /*string*/  $content;                            // Inhalt


   // Getter
   public function getFile()        { return $this->file;             }
   public function getFileName()    { return $this->file['name'    ]; }
   public function getFileType()    { return $this->file['type'    ]; }
   public function getFileTmpName() { return $this->file['tmp_name']; }
   public function getFileError()   { return $this->file['error'   ]; }
   public function getFileSize()    { return $this->file['size'    ]; }
   public function getContent()     { return $this->content;          }


   /**
    * Liest die übergebenen Request-Parameter in das Form-Objekt ein.
    */
   protected function populate(Request $request) {
      if ($request->isPost()) {
         // HTML-Formular
         if ($request->getContentType() == 'multipart/form-data') {
            if (isSet($_FILES['file']))
               $this->file = $_FILES['file'];
         }

         // MQL-API
         else if ($request->getContentType() == 'text/plain') {
            // Daten in temporäre Datei schreiben und $_FILES-Array emulieren
            $content = $request->getContent();

            $tmpFile = tempNam(ini_get('upload_tmp_dir'), 'php.tmp');
            $hFile   = fOpen($tmpFile, 'wb');
            fWrite($hFile, $content);
            fClose($hFile);

            $this->file['name'    ] = null;
            $this->file['type'    ] = $request->getContentType();
            $this->file['tmp_name'] = $tmpFile;
            $this->file['error'   ] = 0;
            $this->file['size'    ] = strLen($content);
         }
      }
   }


   /**
    * Validiert die übergebenen Parameter syntaktisch.
    *
    * @return boolean - TRUE, wenn die übergebenen Parameter gültig sind,
    *                   FALSE andererseits
    */
   public function validate() {
      $request = $this->request;

      $file =& $this->file;
      if (empty($file)) {
         $request->setActionError('', '100: data file missing');
      }
      elseif ($file['error'] > 0) {
         $errors = array(UPLOAD_ERR_OK         => '101: success (UPLOAD_ERR_OK)'                                          ,
                         UPLOAD_ERR_INI_SIZE   => '101: upload error, file too big (UPLOAD_ERR_INI_SIZE)'                 ,
                         UPLOAD_ERR_FORM_SIZE  => '101: upload error, file too big (UPLOAD_ERR_FORM_SIZE)'                ,
                         UPLOAD_ERR_PARTIAL    => '101: partial file upload error, try again (UPLOAD_ERR_PARTIAL)'        ,
                         UPLOAD_ERR_NO_FILE    => '101: error while uploading the file (UPLOAD_ERR_NO_FILE)'              ,
                         UPLOAD_ERR_NO_TMP_DIR => '101: read/write error while uploading the file (UPLOAD_ERR_NO_TMP_DIR)',
                         UPLOAD_ERR_CANT_WRITE => '101: read/write error while uploading the file (UPLOAD_ERR_CANT_WRITE)',
                         UPLOAD_ERR_EXTENSION  => '101: error while uploading the file (UPLOAD_ERR_EXTENSION)'            ,
         );
         $request->setActionError('', $errors[$file['error']]);
      }
      elseif ($request->getContentType()=='multipart/form-data' && !is_uploaded_file($file['tmp_name'])) {
         Logger ::log('Possible file upload attack:  is_uploaded_file("'.$file['tmp_name'].'") => false', L_WARN, __CLASS__);
         $request->setActionError('', '101: error while uploading the file');
      }
      elseif ($file['size'] == 0) {
         $request->setActionError('', '100: data file empty');
      }
      if ($request->isActionError())
         return false;

      $this->content = file_get_contents($file['tmp_name']);


      // Datei einlesen und syntaktisch validieren
      $content = $this->content;
      if (strLen($content) == 0) {
         $request->setActionError('', '100: data file empty');
         return false;
      }

      $lines    = explode("\n", $content);
      $sections = array('account'=> null, 'data'=> null);
      $section  = null;
      date_default_timezone_set('GMT');                        // MetaTrader kennt keine Zeitzonen, alle Zeitangaben sind in GMT


      // Inhalt der Datei syntaktisch validieren und dabei gleichzeitig die Rohdaten einlesen
      foreach ($lines as $i => &$line) {
         $line = trim($line, " \r\n");
         if ($line==='' || $line{0}=='#')                      // Leerzeilen und Kommentare überspringen
            continue;

         if (preg_match('/^\[(\w+)\]/i', $line, $matches)) {   // Abschnittsnamen analysieren
            $section = strToLower($matches[1]);
            if (($section=='account' || $section=='data') && !isSet($sections[$section])) {
               $sections[$section] = array();
               continue;
            }
            $section = null;                                   // unbekannte Abschnitte und mehrfache Vorkommen gültiger Abschnitte überspringen
         }
         if (!$section)                                        // alle Zeilen außerhalb gültiger Abschnitte überspringen
            continue;

         // Abschnitt [account]
         if ($section == 'account') {
            $values = explode("\t", $line);
            if (sizeOf($values) != 3) {
               $request->setActionError('', '100: invalid file format (unexpected number of columns in line '.($i+1).')');
               return false;
            }

            $accountCompany = trim($values[0]);
            $accountNumber  =      $values[1];
            $accountBalance =      $values[2];

            if ($accountNumber !== (string)(int)$accountNumber) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',2)');
               return false;
            }
            $accountNumber = (int) $accountNumber;

            if ($accountBalance != (string)(float)$accountBalance) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',4)');
               return false;
            }
            $accountBalance = (float) $accountBalance;

            // Datenfelder zwischenspeichern
            $sections['account'][] = array(HC_ACCOUNTCOMPANY => $accountCompany,    //  0
                                           HC_ACCOUNTNUMBER  => $accountNumber,     //  1
                                           HC_ACCOUNTBALANCE => $accountBalance,    //  2
                                          );
            // Abschnitt [account] nach der ersten Datenzeile abbrechen
            $section = null;
            continue;
         }

         // Abschnitt [data]
         if ($section == 'data') {
            $values = explode("\t", $line);
            if (sizeOf($values) != 20) {
               $request->setActionError('', '100: invalid file format (unexpected number of columns in line '.($i+1).')');
               return false;
            }

            $ticket              =      $values[ 0];
            $openTime            =      $values[ 1];
            $openTimestamp       =      $values[ 2];
            $description         =      $values[ 3];
            $type                =      $values[ 4];
            $units               =      $values[ 5];
            $symbol              =      $values[ 6];
            $openPrice           =      $values[ 7];
            $stopLoss            =      $values[ 8];
            $takeProfit          =      $values[ 9];
            $expirationTime      =      $values[10];
            $expirationTimestamp =      $values[11];
            $closeTime           =      $values[12];
            $closeTimestamp      =      $values[13];
            $closePrice          =      $values[14];
            $commission          =      $values[15];
            $swap                =      $values[16];
            $profit              =      $values[17];
            $magicNumber         =      $values[18];
            $comment             = trim($values[19]);

            if ($ticket !== (string)(int)$ticket) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',1)');
               return false;
            }
            $ticket = (int) $ticket;

            if ($openTimestamp !== (string)(int)$openTimestamp) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',3)');
               return false;
            }
            $openTimestamp = (int) $openTimestamp;

            if ($type!==(string)(int)$type || !Validator ::isOperationType((int) $type)) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',5)');
               return false;
            }
            $type = (int) $type;
            if ($type==OP_BUYLIMIT || $type==OP_SELLLIMIT || $type==OP_BUYSTOP || $type==OP_SELLSTOP)
               continue;

            if ($description != ViewHelper ::$operationTypes[$type]) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',4)');
               return false;
            }

            if ($units !== (string)(int)$units) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',6)');
               return false;
            }
            $units = (int) $units;

            if ($closeTimestamp !== (string)(int)$closeTimestamp) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',14)');
               return false;
            }
            $closeTimestamp = (int) $closeTimestamp;

            if ($commission != (string)(float)$commission) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',16)');
               return false;
            }
            $commission = (float) $commission;

            if ($swap != (string)(float)$swap) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',17)');
               return false;
            }
            $swap = (float) $swap;

            if ($profit != (string)(float)$profit) {
               $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',18)');
               return false;
            }
            $profit = (float) $profit;

            if ($type==OP_BALANCE || $type==OP_CREDIT) { // für Balance und Credit-Werte nichtzutreffende Felder auf NULL setzen
               $symbol              = null;
               $openPrice           = null;
               $stopLoss            = null;
               $takeProfit          = null;
               $closePrice          = null;
               $expirationTime      = null;
               $expirationTimestamp = null;
               $magicNumber         = null;
            }
            else {
               if (!Validator ::isInstrument($symbol)) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',7)');
                  return false;
               }

               if ($openPrice != (string)(float)$openPrice) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',8)');
                  return false;
               }
               $openPrice = (float) $openPrice;

               if (strLen($stopLoss) && $stopLoss!=(string)(float)$stopLoss) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',9)');
                  return false;
               }
               $stopLoss = strLen($stopLoss) ? (float) $stopLoss : null;

               if (strLen($takeProfit) && $takeProfit!=(string)(float)$takeProfit) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',10)');
                  return false;
               }
               $takeProfit = strLen($takeProfit) ? (float) $takeProfit : null;

               if (strLen($expirationTimestamp) && $expirationTimestamp!==(string)(int)$expirationTimestamp) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',12)');
                  return false;
               }
               $expirationTimestamp = strLen($expirationTimestamp) ? (int) $expirationTimestamp : null;

               if ($closePrice != (string)(float)$closePrice) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',15)');
                  return false;
               }
               $closePrice = (float) $closePrice;

               if (strLen($magicNumber) && $magicNumber!==(string)(int)$magicNumber) {
                  $request->setActionError('', '100: invalid file format (unexpected value in line '.($i+1).',19)');
                  return false;
               }
               $magicNumber = strLen($magicNumber) ? (int) $magicNumber : null;
            }

            // Datenfelder zwischenspeichern
            $sections['data'][] = array(HC_TICKET         => $ticket,               //  0
                                        HC_OPENTIME       => $openTimestamp,        //  1
                                        HC_TYPE           => $type,                 //  2
                                        HC_UNITS          => $units,                //  3
                                        HC_SYMBOL         => $symbol,               //  4
                                        HC_OPENPRICE      => $openPrice,            //  5
                                        HC_STOPLOSS       => $stopLoss,             //  6
                                        HC_TAKEPROFIT     => $takeProfit,           //  7
                                        HC_EXPIRATIONTIME => $expirationTimestamp,  //  8
                                        HC_CLOSETIME      => $closeTimestamp,       //  9
                                        HC_CLOSEPRICE     => $closePrice,           // 10
                                        HC_COMMISSION     => $commission,           // 11
                                        HC_SWAP           => $swap,                 // 12
                                        HC_PROFIT         => $profit,               // 13
                                        HC_MAGICNUMBER    => $magicNumber,          // 14
                                        HC_COMMENT        => $comment,              // 15
                                       );
         }
      }

      //echoPre(sizeOf($sections['account']).' account info row');
      //echoPre(sizeOf($sections['data'   ]).' data rows'       );

      return !$request->isActionError();
   }


   /**
    * Destructor
    */
   public function __destruct() {
      try {
         // tmp. Dateien manuell löschen, da $FILES-Array u.U. emuliert sein kann
         if (isSet($this->file['tmp_name']) && is_file($this->file['tmp_name']))
            unlink($this->file['tmp_name']);
      }
      catch (Exception $ex) {
         Logger ::handleException($ex, true);
         throw $ex;
      }
   }
}
?>
