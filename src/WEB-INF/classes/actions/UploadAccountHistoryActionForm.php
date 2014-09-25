<?
/**
 * UploadAccountHistoryActionForm
 *
 * Verarbeitet sowohl Uploads per HTML-Formular (multipart/form-data) als auch per MQL-Schnittstelle (text/plain).
 * Verarbeitet noch keine komprimierten Daten.
 */
class UploadAccountHistoryActionForm extends ActionForm {


   private /*mixed[]*/ $file = array('name'     => null,    // Daten der hochgeladenen Datei
                                     'type'     => null,
                                     'tmp_name' => null,
                                     'error'    => null,
                                     'size'     => null);

   private /*string*/  $accountCompany;
   private /*string*/  $accountNumber;
   private /*float*/   $accountBalance;
   private /*mixed[]*/ $data;                               // geparster Inhalt der Datei


   // Getter
   public function  getFile()           { return $this->file;             }
   public function  getFileName()       { return $this->file['name'    ]; }
   public function  getFileType()       { return $this->file['type'    ]; }
   public function  getFileTmpName()    { return $this->file['tmp_name']; }
   public function  getFileError()      { return $this->file['error'   ]; }
   public function  getFileSize()       { return $this->file['size'    ]; }
   public function &getFileData()       { return $this->data;             }

   public function  getAccountCompany() { return $this->accountCompany;   }
   public function  getAccountNumber()  { return $this->accountNumber;    }
   public function  getAccountBalance() { return $this->accountBalance;   }



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
         $request->setActionError('', '100: Data file missing');
      }
      elseif ($file['error'] > 0) {
         $errors = array(UPLOAD_ERR_OK         => '101: Success (UPLOAD_ERR_OK)'                                          ,
                         UPLOAD_ERR_INI_SIZE   => '101: Upload error, file too big (UPLOAD_ERR_INI_SIZE)'                 ,
                         UPLOAD_ERR_FORM_SIZE  => '101: Upload error, file too big (UPLOAD_ERR_FORM_SIZE)'                ,
                         UPLOAD_ERR_PARTIAL    => '101: Partial file upload error, try again (UPLOAD_ERR_PARTIAL)'        ,
                         UPLOAD_ERR_NO_FILE    => '101: Error while uploading the file (UPLOAD_ERR_NO_FILE)'              ,
                         UPLOAD_ERR_NO_TMP_DIR => '101: Read/write error while uploading the file (UPLOAD_ERR_NO_TMP_DIR)',
                         UPLOAD_ERR_CANT_WRITE => '101: Read/write error while uploading the file (UPLOAD_ERR_CANT_WRITE)',
                         UPLOAD_ERR_EXTENSION  => '101: Error while uploading the file (UPLOAD_ERR_EXTENSION)'            ,
         );
         $request->setActionError('', $errors[$file['error']]);
      }
      elseif ($request->getContentType()=='multipart/form-data' && !is_uploaded_file($file['tmp_name'])) {
         Logger ::log('Possible file upload attack:  is_uploaded_file("'.$file['tmp_name'].'") => false', L_WARN, __CLASS__);
         $request->setActionError('', '101: Error while uploading the file');
      }
      elseif ($file['size'] == 0) {
         $request->setActionError('', '100: Data file empty');
      }
      if ($request->isActionError())
         return false;


      // Datei einlesen und syntaktisch validieren
      $lines = file($file['tmp_name']);
      if (sizeOf($lines) == 0) {
         $request->setActionError('', '100: Data file empty');
         return false;
      }
      $sections = array('account'=> false, 'data'=> false);
      $section  = null;
      $data     = array();

      foreach ($lines as $i => &$line) {
         $line = trim($line, " \r\n");
         if ($line==='' || $line{0}=='#')                      // Leerzeilen und Kommentare überspringen
            continue;

         if (preg_match('/^\[(\w+)\]/i', $line, $matches)) {   // Abschnittsnamen analysieren
            $section = strToLower($matches[1]);
            if (($section=='account' || $section=='data') && !$sections[$section]) {
               $sections[$section] = true;
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
               $request->setActionError('', '102: Invalid file format (unexpected number of columns in line '.($i+1).')');
               return false;
            }
            $this->accountCompany = trim($values[0]);

            $accountNumber = $values[1];
            if ($accountNumber !== (string)(int)$accountNumber) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',2)');
               return false;
            }
            $this->accountNumber = $accountNumber;

            $accountBalance = $values[2];
            if ($accountBalance != (string)(float)$accountBalance) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',4)');
               return false;
            }
            $this->accountBalance = (float) $accountBalance;

            // Abschnitt [account] nach der ersten Datenzeile abbrechen
            $section = null;
            continue;
         }

         // Abschnitt [data]
         if ($section == 'data') {
            $values = explode("\t", $line);
            if (sizeOf($values) != 16) {
               $request->setActionError('', '102: Invalid file format (unexpected number of columns in line '.($i+1).')');
               return false;
            }

            $ticket         =      $values[ 0];
            $openTime       =      $values[ 1];
            $openTimestamp  =      $values[ 2];
            $description    =      $values[ 3];
            $type           =      $values[ 4];
            $units          =      $values[ 5];
            $symbol         =      $values[ 6];
            $openPrice      =      $values[ 7];
            $closeTime      =      $values[ 8];
            $closeTimestamp =      $values[ 9];
            $closePrice     =      $values[10];
            $commission     =      $values[11];
            $swap           =      $values[12];
            $profit         =      $values[13];
            $magicNumber    =      $values[14];
            $comment        = trim($values[15]);

            if ($ticket!==(string)(int)$ticket || (int)$ticket <= 0) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',1)');
               return false;
            }
            $ticket = (int) $ticket;

            if ($openTimestamp!==(string)(int)$openTimestamp || (int)$openTimestamp <= 0) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',3)');
               return false;
            }
            $openTimestamp = (int) $openTimestamp;

            if ($type!==(string)(int)$type || !Validator ::isMT4OperationType((int) $type)) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',5)');
               return false;
            }
            $type = (int) $type;
            if ($type==OP_BUYLIMIT || $type==OP_SELLLIMIT || $type==OP_BUYSTOP || $type==OP_SELLSTOP)
               continue;

            if ($units!==(string)(int)$units || (int)$units < 0) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',6)');
               return false;
            }
            $units = (int) $units;

            if ($closeTimestamp!==(string)(int)$closeTimestamp || (int)$closeTimestamp <= 0) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',10)');
               return false;
            }
            $closeTimestamp = (int) $closeTimestamp;

            if ($commission != (string)(float)$commission) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',12)');
               return false;
            }
            $commission = (float) $commission;

            if ($swap != (string)(float)$swap) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',13)');
               return false;
            }
            $swap = (float) $swap;

            if ($profit != (string)(float)$profit) {
               $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',14)');
               return false;
            }
            $profit = (float) $profit;

            if ($type==OP_BALANCE || $type==OP_CREDIT) { // für Balance und Credit-Werte nichtzutreffende Felder auf NULL setzen
               $symbol      = null;
               $openPrice   = null;
               $closePrice  = null;
               $magicNumber = null;
               if ($units > 0) {
                  $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',6)');
                  return false;
               }
            }
            else {
               if (!Validator ::isInstrument($symbol)) {
                  $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',7)');
                  return false;
               }

               if ($openPrice!=(string)(float)$openPrice || (float)$openPrice <= 0) {
                  $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',8)');
                  return false;
               }
               $openPrice = (float) $openPrice;

               if ($closePrice!=(string)(float)$closePrice || (float)$closePrice <= 0) {
                  $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',11)');
                  return false;
               }
               $closePrice = (float) $closePrice;

               if (strLen($magicNumber) && ($magicNumber!==(string)(int)$magicNumber || (int)$magicNumber <= 0)) {
                  $request->setActionError('', '103: Invalid file format (unexpected value in line '.($i+1).',15)');
                  return false;
               }
               $magicNumber = strLen($magicNumber) ? (int) $magicNumber : null;
            }

            // Datenfelder zwischenspeichern
            $data[] = array(AH_TICKET      => $ticket,            //  0
                            AH_OPENTIME    => $openTimestamp,     //  1
                            AH_TYPE        => $type,              //  2
                            AH_UNITS       => $units,             //  3
                            AH_SYMBOL      => $symbol,            //  4
                            AH_OPENPRICE   => $openPrice,         //  5
                            AH_CLOSETIME   => $closeTimestamp,    //  6
                            AH_CLOSEPRICE  => $closePrice,        //  7
                            AH_COMMISSION  => $commission,        //  8
                            AH_SWAP        => $swap,              //  9
                            AH_PROFIT      => $profit,            // 10
                            AH_MAGICNUMBER => $magicNumber,       // 11
                            AH_COMMENT     => $comment,           // 12
                           );
         }
      }
      $this->data =& $data;

      return !$request->isActionError();
   }


   /**
    * Destructor
    */
   public function __destruct() {
      try {
         // tmp. Dateien manuell löschen, da $FILES-Array u.U. emuliert sein kann
         if (is_file($this->file['tmp_name']))
            unlink($this->file['tmp_name']);
      }
      catch (Exception $ex) {
         Logger ::handleException($ex, $ignoreIfNotInShutdown=true);
         throw $ex;
      }
   }
}
?>
