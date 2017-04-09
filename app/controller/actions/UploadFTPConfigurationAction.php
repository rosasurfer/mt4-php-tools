<?php
namespace rosasurfer\xtrade\controller\actions;

use rosasurfer\exception\IOException;
use rosasurfer\log\Logger;

use rosasurfer\ministruts\Action;
use rosasurfer\ministruts\Request;
use rosasurfer\ministruts\Response;

use rosasurfer\util\System;

use rosasurfer\xtrade\controller\forms\UploadFTPConfigurationActionForm;
use rosasurfer\xtrade\Tools;


/**
 * UploadFTPConfigurationAction
 */
class UploadFTPConfigurationAction extends Action {


    /**
     * {@inheritdoc}
     */
    public function execute(Request $request, Response $response) {
        /** @var UploadFTPConfigurationActionForm $form */
        $form = $this->form;

        if ($form->validate()) {
            try {
                // Dateinamen bestimmen
                $company   = $form->getCompany();
                $account   = $form->getAccount();
                $symbol    = $form->getSymbol();
                $directory = Tools::getConfigPath('strategies.config.ftp').'/'.$company.'/'.$account.'/'.$symbol;
                $filename  = $directory.'/'.$form->getFileName();

                // Datei speichern
                mkDirWritable($directory, 0700);
                if (!copy($form->getFileTmpName(), $filename)) throw new IOException('Error copying src="'.$form->getFileTmpName().'" to dest="'.$filename.'"');

                echo("200\n");
                return null;
            }
            catch (\Exception $ex) {
                Logger::log('System not available', L_ERROR, ['exception'=>$ex]);
                $request->setActionError('', '500: Server error, try again later.');
            }
        }

        echo($request->getActionError()."\n") ;
        return null;
    }
}
