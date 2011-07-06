<?php
/**
 * UploadFTPConfigurationActionForm
 */
class UploadFTPConfigurationActionForm extends ActionForm {


   private /*mixed[]*/ $file = array('name'     => null,
                                     'type'     => null,
                                     'tmp_name' => null,
                                     'error'    => null,
                                     'size'     => null);

   // Getter
   public function  getFile()        { return $this->file;             }
   public function  getFileName()    { return $this->file['name'    ]; }
   public function  getFileType()    { return $this->file['type'    ]; }
   public function  getFileTmpName() { return $this->file['tmp_name']; }
   public function  getFileError()   { return $this->file['error'   ]; }
   public function  getFileSize()    { return $this->file['size'    ]; }


   /**
    * Liest die übergebenen Request-Parameter in das Form-Objekt ein.
    */
   protected function populate(Request $request) {
         // Hochgeladene Datei in temporäre Datei schreiben und dabei $_FILES-Array emulieren
      if ($request->isPost() && $request->getContentType()=='text/plain') {
         $tmpName = tempNam(ini_get('upload_tmp_dir'), 'php.tmp');
         $hFile   = fOpen($tmpName, 'wb');
         $bytes   = fWrite($hFile, $request->getContent());
         fClose($hFile);

         $this->file['name'    ] = (isSet($_REQUEST['name']) && is_string($_REQUEST['name'])) ? trim($_REQUEST['name']) : null;
         $this->file['type'    ] = $request->getContentType();
         $this->file['tmp_name'] = $tmpName;
         $this->file['error'   ] = 0;
         $this->file['size'    ] = $bytes;
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
      elseif ($file['size'] == 0) {
         $request->setActionError('', '100: data file empty');
      }
      elseif (!preg_match('/^FTP\.\d{4,}\.set$/', $file['name'], $matches)) {
         $request->setActionError('', '100: illegal file name');
      }

      return !$request->isActionError();
   }


   /**
    * Destructor
    */
   public function __destruct() {
      // temporäre Datei manuell löschen, da $FILES-Array emuliert ist
      try {
         if (is_file($this->file['tmp_name']))
            unlink($this->file['tmp_name']);
      }
      catch (Exception $ex) {
         Logger ::handleException($ex, true);
         throw $ex;
      }
   }
}
?>
