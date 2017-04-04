<?php
namespace rosasurfer\trade\controller\actions;

use rosasurfer\exception\IOException;
use rosasurfer\log\Logger;

use rosasurfer\ministruts\Action;
use rosasurfer\ministruts\ActionForward;
use rosasurfer\ministruts\Request;
use rosasurfer\ministruts\Response;

use rosasurfer\trade\controller\forms\UploadAccountHistoryActionForm;
use rosasurfer\trade\myfx\MyFX;

use rosasurfer\util\System;


/**
 * UploadFTPConfigurationAction
 */
class UploadFTPConfigurationAction extends Action {

    /**
     * Fuehrt die Action aus.
     *
     * @return ActionForward
     */
    public function execute(Request $request, Response $response) {
        /** @var UploadAccountHistoryActionForm $form */
        $form = $this->form;

        if ($form->validate()) {
            try {
                // Dateinamen bestimmen
                $company   = $form->getCompany();
                $account   = $form->getAccount();
                $symbol    = $form->getSymbol();
                $directory = MyFX::getConfigPath('strategies.config.ftp').'/'.$company.'/'.$account.'/'.$symbol;
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
