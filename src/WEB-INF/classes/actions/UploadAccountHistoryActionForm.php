<?php
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
            $units               = $values[ 5];
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

            if ($ticket !== (string)(int)$ticket) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',1)');
               return false;
            }
            $ticket = (int) $ticket;

            if ($openTimestamp !== (string)(int)$openTimestamp) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',3)');
               return false;
            }
            $openTimestamp = (int) $openTimestamp;

            if ($openTime != date('Y.m.d H:i:s', $openTimestamp)) {  // $openTime 1 x prüfen, um Abweichungen der MT4-Timezone-Implementierung zu erkennen
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',2)');
               return false;
            }

            if ($type!==(string)(int)$type || !Validator ::isOperationType((int) $type)) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',5)');
               return false;
            }
            $type = (int) $type;
            if ($type==OP_BUYLIMIT || $type==OP_SELLLIMIT || $type==OP_BUYSTOP || $type==OP_SELLSTOP)
               continue;

            if ($typeStr != ViewHelper ::$operationTypes[$type]) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',4)');
               return false;
            }

            if ($units !== (string)(int)$units) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',6)');
               return false;
            }
            $units = (int) $units;

            if ($closeTimestamp !== (string)(int)$closeTimestamp) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',14)');
               return false;
            }
            $closeTimestamp = (int) $closeTimestamp;

            if ($commission != (string)(float)$commission) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',16)');
               return false;
            }
            $commission = (float) $commission;

            if ($swap != (string)(float)$swap) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',17)');
               return false;
            }
            $swap = (float) $swap;

            if ($profit != (string)(float)$profit) {
               $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',18)');
               return false;
            }
            $profit = (float) $profit;

            if ($type==OP_BALANCE || $type==OP_CREDIT) {
               $symbol              = null;
               $openPrice           = null;
               $stopLoss            = null;
               $takeProfit          = null;
               $closePrice          = null;
               $expirationTime      = null;
               $expirationTimestamp = null;
               $magicNumber         = null;
            }
            else {
               if (!Validator ::isInstrument($symbol)) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',7)');
                  return false;
               }

               if ($openPrice != (string)(float)$openPrice) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',8)');
                  return false;
               }
               $openPrice = (float) $openPrice;

               if (strLen($stopLoss) && $stopLoss!=(string)(float)$stopLoss) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',9)');
                  return false;
               }
               $stopLoss = strLen($stopLoss) ? (float) $stopLoss : null;

               if (strLen($takeProfit) && $takeProfit!=(string)(float)$takeProfit) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',10)');
                  return false;
               }
               $takeProfit = strLen($takeProfit) ? (float) $takeProfit : null;

               if (strLen($expirationTimestamp) && $expirationTimestamp!==(string)(int)$expirationTimestamp) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',12)');
                  return false;
               }
               $expirationTimestamp = strLen($expirationTimestamp) ? (int) $expirationTimestamp : null;

               if ($closePrice != (string)(float)$closePrice) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',15)');
                  return false;
               }
               $closePrice = (float) $closePrice;

               if (strLen($magicNumber) && $magicNumber!==(string)(int)$magicNumber) {
                  $request->setActionError('', '400: invalid file format (unexpected value in line '.($i+1).',19)');
                  return false;
               }
               $magicNumber = strLen($magicNumber) ? (int) $magicNumber : null;
            }

            $comment = trim($comment);
         }
      }
      return !$request->isActionError();
   }
}
?>
