<?php
/**
 * UploadAccountHistoryAction
 */
class UploadAccountHistoryAction extends Action {


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
            $updates = ImportHelper ::updateAccountHistory($form);
            echo('200: '.($updates ? "History successfully updated ($updates)":'History is up to date')."\n");
            return null;
         }
         catch (BusinessRuleException $ex) {
            $request->setActionError('', '500: '.$ex->getMessage());
         }
         catch (Exception $ex) {
            Logger ::log('System not available', $ex, L_ERROR, __CLASS__);
            $request->setActionError('', '500: server error, try again later');
         }
      }

      echo($request->getActionError()."\n") ;
      return null;
   }
}
?>
