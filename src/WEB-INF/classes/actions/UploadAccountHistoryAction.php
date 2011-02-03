<?php
/**
 * UploadAccountHistoryAction
 */
class UploadAccountHistoryAction extends Action {


   private static $messages = array('unknown_account'  => '110: Account unknown or not found.',
                                    'balance_mismatch' => '110: Balance mismatch, more history data needed.',

   );


   /**
    * Führt die Action aus.
    *
    * @return ActionForward
    */
   public function execute(Request $request, Response $response) {
      if ($request->isPost())
         return $this->onPost($request, $response);

      return 'default';
   }


   /**
    * Verarbeitet einen POST-Request.
    *
    * @return ActionForward
    */
   public function onPost(Request $request, Response $response) {
      $form = $this->form;

      if ($form->validate()) {
         try {
            try {
               $updates = ImportHelper ::updateAccountHistory($form);
               if (!$updates) echo("200: History is up to date.\n");                // 200
               else           echo("201: History successfully updated.\n");         // 201
               return null;
            }
            catch (InvalidArgumentException $ex) {}
            catch (BusinessRuleException    $ex) {}
            $key = $ex->getMessage();
            if (!isSet(self::$messages[$key]))
               throw $ex;
            $request->setActionError('', self::$messages[$key]);
         }
         catch (Exception $ex) {
            Logger ::log('System not available', $ex, L_ERROR, __CLASS__);
            $request->setActionError('', '500: Server error, try again later.');    // 500
         }
      }

      echo($request->getActionError()."\n") ;
      return null;
   }
}
?>
