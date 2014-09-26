<?php
/**
 * DownloadFTPConfigurationActionForm
 */
class DownloadFTPConfigurationActionForm extends ActionForm {

   private /*string*/  $company;
   private /*int*/     $account;
   private /*string*/  $symbol;
   private /*int*/     $sequence;

   // Getter
   public function  getCompany()  { return $this->company;  }
   public function  getAccount()  { return $this->account;  }
   public function  getSymbol()   { return $this->symbol;   }
   public function  getSequence() { return $this->sequence; }


   /**
    * Liest die übergebenen Request-Parameter in das Form-Objekt ein.
    */
   protected function populate(Request $request) {
      if (isSet($_REQUEST['company' ]) &&   is_string($_REQUEST['company' ])) $this->company  =       $_REQUEST['company' ];
      if (isSet($_REQUEST['account' ]) && cType_digit($_REQUEST['account' ])) $this->account  = (int) $_REQUEST['account' ];
      if (isSet($_REQUEST['symbol'  ]) &&   is_string($_REQUEST['symbol'  ])) $this->symbol   =       $_REQUEST['symbol'  ];
      if (isSet($_REQUEST['sequence']) && cType_digit($_REQUEST['sequence'])) $this->sequence = (int) $_REQUEST['sequence'];
   }


   /**
    * Validiert die übergebenen Parameter syntaktisch.
    *
    * @return boolean - TRUE, wenn die übergebenen Parameter gültig sind,
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
?>
