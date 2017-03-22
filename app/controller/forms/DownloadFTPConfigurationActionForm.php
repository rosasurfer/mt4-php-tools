<?php
use rosasurfer\ministruts\ActionForm;
use rosasurfer\ministruts\Request;


/**
 * DownloadFTPConfigurationActionForm
 */
class DownloadFTPConfigurationActionForm extends ActionForm {

    private /*string*/ $company;
    private /*int*/    $account;
    private /*string*/ $symbol;
    private /*int*/    $sequence;

    // Getter
    public function  getCompany()  { return $this->company;  }
    public function  getAccount()  { return $this->account;  }
    public function  getSymbol()   { return $this->symbol;   }
    public function  getSequence() { return $this->sequence; }


    /**
     * Liest die uebergebenen Request-Parameter in das Form-Objekt ein.
     */
    protected function populate(Request $request) {
        $this->company  = $request->getParameter('company');
        $this->symbol   = $request->getParameter('symbol' );

        $account        = $request->getParameter('account');
        $this->account  = ctype_digit($account) ? (int) $account : 0;

        $sequence       = $request->getParameter('sequence');
        $this->sequence = ctype_digit($sequence) ? (int) $sequence : 0;
    }


    /**
     * Validiert die uebergebenen Parameter syntaktisch.
     *
     * @return boolean - TRUE, wenn die uebergebenen Parameter gueltig sind,
     *                   FALSE andererseits
     */
    public function validate() {
        $request = $this->request;

        if      (strLen($this->company) == 0) $request->setActionError('company' , 'invalid company name'  );
        else if ($this->account <= 0)         $request->setActionError('account' , 'invalid account number');
        else if (strLen($this->symbol) < 2)   $request->setActionError('symbol'  , 'invalid symbol'        );
        else if ($this->sequence <= 0)        $request->setActionError('sequence', 'invalid sequence id'   );

        return !$request->isActionError();
    }
}
