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
            //PROJECT_DIRECTORY
            $directory = Config ::get('strategies.ftp.directory');
            $file      = $directory.DIRECTORY_SEPARATOR.$form->getFileName();




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
