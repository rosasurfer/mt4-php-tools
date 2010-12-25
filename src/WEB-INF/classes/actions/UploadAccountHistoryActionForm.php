<?
/**
 * UploadAccountHistoryActionForm
 */
class UploadAccountHistoryActionForm extends ActionForm {


   private /*string*/ $content;        // Inhalt der hochgeladenen Datei

   // Getter
   public function getContent() { return $this->content; }


   /**
    * Liest die übergebenen Request-Parameter in das Form-Objekt ein.
    */
   protected function populate(Request $request) {
      if ($request->isPost() && $request->getContentType()=='application/x-www-form-urlencoded') {
         $this->content = $request->getContent();        // Wir erwarten *nicht* den Content-Type "multipart/form-data",
      }                                                  // sondern lesen stattdessen direkt den Request-Body aus.
   }


   /**
    * Validiert die übergebenen Parameter syntaktisch.
    *
    * @return boolean - TRUE, wenn die übergebenen Parameter gültig sind,
    *                   FALSE andererseits
    */
   public function validate() {
      $request = $this->request;
      $content = $this->content;

      if (strLen($content) == 0) {
         $request->setActionError('', '400: invalid request, file content missing');
         return false;
      }

      // Inhalt der Datei syntaktisch validieren
      $lines   = explode("\n", $content);
      $section = null;                       // Abschnittsname

      foreach ($lines as $i => &$line) {
         $line = trim($line, " \r\n");
         // Leerzeilen und Kommentare überspringen
         if ($line==='' || $line{0}=='#')
            continue;

         // Abschnitt erkennen
         if (!$section) {
            if      (String ::startsWith($line, '[account]', true)) $section = '[account]';
            else if (String ::startsWith($line, '[data]'   , true)) $section = '[data]';
            else {
               $request->setActionError('', '400: validation error in line '.($i+1).' (invalid section header found: '.$line.')');
               return false;
            }
         }
         else {
         }




         if ($i === 0) {
            if ($line !== "invoice_id\taktenzeichen") {
               $request->setActionError('', '400: Formatfehler in Datei: ungültiger Header (Zeile '.($i+1).')');
               return false;
            }
            continue;
         }

         $values = explode("\t", $line);
         if (sizeOf($values) != 2) {
            $request->setActionError('', '400: Formatfehler in Datei: ungültiger Datensatz (Zeile '.($i+1).')');
            return false;
         }
         else {
            $invoice       = trim($values[0]);
            $encashmentKey = trim($values[1]);

            if ($invoice=='' || $encashmentKey=='')
               continue;

            if (!Validator ::isInvoiceNo($invoice) || !Validator ::isEncashmentKey($encashmentKey)) {
               $request->setActionError('', '400: Formatfehler in Datei: ungültiger Datensatz (Zeile '.($i+1).')');
               return false;
            }
            $data[] = subStr($invoice, 2)."\t".$encashmentKey;
         }
      }

      // Daten in die temporäre Datei zurückschreiben
      $fH = fOpen($file['tmp_name'], 'wb');
      fWrite($fH, join("\n", $data)."\n");
      fClose($fH);

      unset($lines, $data);

      return !$request->isActionError();
   }
}
?>
