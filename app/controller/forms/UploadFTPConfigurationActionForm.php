<?php
namespace rosasurfer\trade\controller\forms;

use rosasurfer\debug\ErrorHandler;

use rosasurfer\ministruts\ActionForm;
use rosasurfer\ministruts\Request;

use \Exception;


/**
 * UploadFTPConfigurationActionForm
 */
class UploadFTPConfigurationActionForm extends ActionForm {


    private /*string*/  $company;
    private /*int*/     $account;
    private /*string*/  $symbol;
    private /*mixed[]*/ $file = [
        'name'     => null,
        'type'     => null,
        'tmp_name' => null,
        'error'    => null,
        'size'     => null,
    ];

    // Getter
    public function  getCompany()     { return $this->company;          }
    public function  getAccount()     { return $this->account;          }
    public function  getSymbol()      { return $this->symbol;           }
    public function  getFile()        { return $this->file;             }
    public function  getFileName()    { return $this->file['name'    ]; }
    public function  getFileType()    { return $this->file['type'    ]; }
    public function  getFileTmpName() { return $this->file['tmp_name']; }
    public function  getFileError()   { return $this->file['error'   ]; }
    public function  getFileSize()    { return $this->file['size'    ]; }


    /**
     * Liest die uebergebenen Request-Parameter in das Form-Objekt ein.
     */
    protected function populate(Request $request) {
        // Hochgeladene Datei in temporaere Datei schreiben und $_FILES-Array emulieren
        if ($request->isPost() && $request->getContentType()=='text/plain') {
            $this->company = $request->getParameter('company');
            $this->symbol  = $request->getParameter('symbol' );

            $account       = $request->getParameter('account');
            $this->account = ctype_digit($account) ? (int) $account : 0;

            $tmpName = tempNam(ini_get('upload_tmp_dir'), 'php.tmp');
            $hFile   = fOpen($tmpName, 'wb');
            $bytes   = fWrite($hFile, $request->getContent());
            fClose($hFile);

            $this->file['name'    ] = trim($request->getParameter('name'));
            $this->file['type'    ] = $request->getContentType();
            $this->file['tmp_name'] = $tmpName;
            $this->file['error'   ] = 0;
            $this->file['size'    ] = $bytes;
        }
    }


    /**
     * Validiert die uebergebenen Parameter syntaktisch.
     *
     * @return boolean - TRUE, wenn die uebergebenen Parameter gueltig sind,
     *                   FALSE andererseits
     */
    public function validate() {
        $request =  $this->request;
        $file    =& $this->file;

        if      (strLen($this->company) == 0) $request->setActionError('company', '100: invalid company name'  );
        else if ($this->account <= 0)         $request->setActionError('account', '100: invalid account number');
        else if (strLen($this->symbol) < 2)   $request->setActionError('symbol' , '100: invalid symbol'        );
        else if (empty($file))                $request->setActionError(''       , '100: data file missing'     );
        elseif ($file['size'] == 0)           $request->setActionError(''       , '100: data file empty'       );
        elseif (!preg_match('/^FTP\.\d{4,}\.set$/', $file['name'], $matches)) {
            $request->setActionError('name', '100: illegal file name');
        }

        return !$request->isActionError();
    }


    /**
     * Destructor
     */
    public function __destruct() {
        // Attempting to throw an exception from a destructor during script shutdown causes a fatal error.
        // @see http://php.net/manual/en/language.oop5.decon.php
        try {
            // temporaere Datei manuell loeschen, da $FILES-Array emuliert ist
            if (is_file($this->file['tmp_name']))
                unlink($this->file['tmp_name']);
        }
        catch (Exception $ex) {
            throw ErrorHandler::handleDestructorException($ex);
        }
    }
}
