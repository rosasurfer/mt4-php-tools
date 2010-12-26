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
         $request->setActionError('', '400: file content missing');
         return false;
      }

      $lines    = explode("\n", $content);
      $sections = array();
      $section  = null;
      date_default_timezone_set('GMT');      // MetaTrader kennt keine Zeitzonen und interpretiert alle Zeitangaben als GMT


      // Inhalt der Datei syntaktisch validieren und dabei gleichzeitig die Rohdaten einlesen
      foreach ($lines as $i => &$line) {
         $line = trim($line, " \r\n");
         if ($line==='' || $line{0}=='#')                      // Leerzeilen und Kommentare überspringen
            continue;

         if (preg_match('/^\[(\w+)\]/i', $line, $matches)) {   // Abschnittsnamen analysieren
            $section = strToLower($matches[1]);
            if (($section=='account' || $section=='data') && !isSet($sections[$section])) {
               $sections[$section] = array();
               //echo("\n[$section] section\n");
               continue;
            }
            $section = null;                                   // unbekannte Abschnitte und mehrfache Vorkommen gültiger Abschnitte überspringen
         }
         if (!$section)                                        // alle Zeilen außerhalb gültiger Abschnitte überspringen
            continue;

         // Abschnitt [account]
         if ($section == 'account') {
            $sections['account'][] = $line;
            //echo("$line\n");
            continue;
         }

         // Abschnitt [data]
         if ($section == 'data') {
            $values = explode("\t", $line);
            if (sizeOf($values) != 20) {
               $request->setActionError('', '400: invalid file format (unexpected number of columns in line '.($i+1).')');
               return false;
            }

            $ticket              = $values[ 0];
            $openTime            = $values[ 1];
            $openTimestamp       = $values[ 2];
            $typeStr             = $values[ 3];
            $type                = $values[ 4];
            $size                = $values[ 5];
            $symbol              = $values[ 6];
            $openPrice           = $values[ 7];
            $stopLoss            = $values[ 8];
            $takeProfit          = $values[ 9];
            $expirationTime      = $values[10];
            $expirationTimestamp = $values[11];
            $closeTime           = $values[12];
            $closeTimestamp      = $values[13];
            $closePrice          = $values[14];
            $commission          = $values[15];
            $swap                = $values[16];
            $profit              = $values[17];
            $magicNumber         = $values[18];
            $comment             = $values[19];

            if (!cType_digit($ticket)) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',1)');
               return false;
            }
            $ticket = (int) $ticket;

            if (!cType_digit($openTimestamp)) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',3)');
               return false;
            }
            $openTimestamp = (int) $openTimestamp;

            if ($openTime != date('Y.m.d H:i:s', $openTimestamp)) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',2)');
               return false;
            }

            if (!cType_digit($type) || !Validator ::isOperationType((int) $type)) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',5)');
               return false;
            }
            $type = (int) $type;

            if ($typeStr != ViewHelper ::$operationTypes[$type]) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',4)');
               return false;
            }

            if (!cType_digit($size)) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',6)');
               return false;
            }
            $size = (int) $size;


            /*
            Size  Symbol   OpenPrice   StopLoss    TakeProfit  ExpirationTime ExpirationTimestamp  CloseTime            CloseTimestamp ClosePrice  Commission  Swap  Profit   MagicNumber Comment
            11    GBPJPY   130.246     130.196                                                     2010.11.30 19:50:31  1291146631     130.196     -88.00      0.00  -657.74              [tp]

            if (cType_digit($values[2])) echo(date('Y-m-d H:i:s', $values[2]).'   '."$line\n");
            else                         echo(date('Y-m-d H:i:s',          0).'   '."$line\n");
            */
            echo("$line\n");
         }
      }
      return !$request->isActionError();
   }
}
?>
