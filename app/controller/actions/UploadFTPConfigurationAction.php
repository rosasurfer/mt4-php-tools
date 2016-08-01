<?php
use rosasurfer\exception\IOException;
use rosasurfer\ministruts\Request;


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
            $directory = MyFX ::getConfigPath('strategies.config.ftp').'/'.$company.'/'.$account.'/'.$symbol;
            $filename  = $directory.'/'.$form->getFileName();

            // Datei speichern
            mkDirWritable($directory, 0700);
            if (!copy($form->getFileTmpName(), $filename)) throw new IOException('Error copying src="'.$form->getFileTmpName().'" to dest="'.$filename.'"');

            echo("200\n");
            return null;
         }
         catch (\Exception $ex) {
            Logger::log('System not available', $ex, L_ERROR, __CLASS__);
            $request->setActionError('', '500: Server error, try again later.');
         }
      }

      echo($request->getActionError()."\n") ;
      return null;
   }
}
