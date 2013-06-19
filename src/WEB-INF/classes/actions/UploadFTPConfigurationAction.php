<?php
/**
 * UploadFTPConfigurationAction
 */
class UploadFTPConfigurationAction extends Action {

   /**
    * Führt die Action aus.
    *
    * @return ActionForward
    */
   public function execute(Request $request, Response $response) {
      $form = $this->form;

      if ($form->validate()) {
         try {
            // Dateinamen bestimmen
            $company   = $form->getCompany();
            $account   = $form->getAccount();
            $symbol    = $form->getSymbol();
            $directory = MyFXHelper ::getAbsoluteConfigPath('strategies.config.ftp').'/'.$company.'/'.$account.'/'.$symbol;
            $filename  = $directory.'/'.$form->getFileName();

            // ggf. Zielverzeichnis erzeugen
            if (is_file($directory))                                   throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'" (is file)');
            if (!is_dir($directory) && !mkDir($directory, 0700, true)) throw new plInvalidArgumentException('Cannot create directory "'.$directory.'"');
            if (!is_writable($directory))                              throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'"');

            // Datei speichern
            if (!copy($form->getFileTmpName(), $filename)) throw new IOException('Error copying src="'.$form->getFileTmpName().'" to dest="'.$filename.'"');

            echo("200\n");
            return null;
         }
         catch (Exception $ex) {
            Logger ::log('System not available', $ex, L_ERROR, __CLASS__);
            $request->setActionError('', '500: Server error, try again later.');
         }
      }

      echo($request->getActionError()."\n") ;
      return null;
   }
}
?>
