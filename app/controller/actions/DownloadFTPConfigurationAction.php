<?php
namespace rosasurfer\trade\controller\actions;

use \MyFX;

use rosasurfer\log\Logger;

use rosasurfer\ministruts\Action;
use rosasurfer\ministruts\ActionForward;
use rosasurfer\ministruts\Request;
use rosasurfer\ministruts\Response;

use rosasurfer\util\System;


/**
 * DownloadFTPConfigurationAction
 */
class DownloadFTPConfigurationAction extends Action {


    /**
     * Fuehrt die Action aus.
     *
     * @return ActionForward
     */
    public function execute(Request $request, Response $response) {
        $form = $this->form;

        if ($form->validate()) {
            try {
                $company   = $form->getCompany();
                $account   = $form->getAccount();
                $symbol    = $form->getSymbol();
                $sequence  = $form->getSequence();
                $directory = MyFX::getConfigPath('strategies.config.ftp').'/'.$company.'/'.$account.'/'.$symbol;
                $file      = 'FTP.'.$sequence.'.set';

                if (is_file($directory.'/'.$file)) {
                    $content = file_get_contents($directory.'/'.$file, false);

                    header('Content-Type: text/plain');
                    header('Content-Length: '.strLen($content));
                    header('Accept-Ranges: bytes');
                    header('Content-Disposition: attachment; filename="'.$file.'"');
                    header('Content-Description: '.$file);
                    header('Cache-Control: private');
                    header('Pragma: private');

                    echo($content);
                    return null;
                }
                $request->setActionError('', '404: File not found');
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
