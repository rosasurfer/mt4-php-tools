<?
/**
 * MyFX related functionality
 */
class MyFX extends StaticClass {


   /**
    * Gibt den absoluten Pfad der unter dem angegebenen Schlüssel konfigurierten Pfadeinstellung zurück.
    * Ist ein relativer Pfad konfiguriert, wird der Pfad als relativ zu APPLICATION_ROOT interpretiert.
    *
    * @param  string $key - Schlüssel
    *
    * @return string - absoluter Pfad mit Forward-Slashes (auch unter Windows)
    *
    * @throws plRuntimeException - wenn unter dem angegebenen Schlüssel keine Pfadeinstellung existiert
    */
   public static function getAbsoluteConfigPath($key) {
      if (!is_string($key)) throw new IllegalTypeException('Illegal type of argument $key: '.getType($key));

      $directory = str_replace('\\', '/', Config ::get($key));       // Backslashes ersetzen

      if (WINDOWS) {
         if (!preg_match('/^[a-z]:/i', $directory))
            $directory = APPLICATION_ROOT.($directory{0}=='/'?'':'/').$directory;
      }
      else if ($directory{0} != '/') {
         $directory = APPLICATION_ROOT.'/'.$directory;
      }

      return str_replace('\\', '/', $directory);                     // Backslashes in APPLICATION_ROOT ersetzen
   }


   /**
    * Formatiert einen Timestamp als FXT-Zeit.
    *
    * @param  int $timestamp - Zeitpunkt
    *
    * @return string - FXT-String
    */
   public static function fxtDate($timestamp) {
      if (!is_int($timestamp)) throw new IllegalTypeException('Illegal type of argument $timestamp: '.getType($timestamp));

      $oldTimezone = date_default_timezone_get();
      try {
         date_default_timezone_set('America/New_York');
         $result = date('Y-m-d H:i:s', $timestamp + 7*HOURS);
         date_default_timezone_set($oldTimezone);
      }
      catch(Exception $ex) { date_default_timezone_set($oldTimezone); throw $ex; }

      return $result;
   }


   /**
    * Handler für PositionOpen-Events.
    *
    * @param  OpenPosition $position - die geöffnete Position
    */
   public static function onPositionOpen(OpenPosition $position) {
      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = 'position opened: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().'  TP: '.ifNull($position->getTakeProfit(),'-').'  SL: '.ifNull($position->getStopLoss(), '-').'  ('.$position->getOpenTime('H:i:s').')';
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      $mailMsg = $signal->getName().' Open '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice();
      foreach (self ::getMailSignalReceivers() as $receiver) {
         mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
      }


      // Benachrichtigung per SMS
      $smsMsg = 'Open '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice();
      foreach (self ::getSmsSignalReceivers() as $receiver) {
         sendSms($receiver, $signal, $smsMsg);
      }
   }


   /**
    * Handler für PositionModify-Events.
    *
    * @param  OpenPosition $position - die modifizierte Position
    */
   public static function onPositionModify(OpenPosition $position) {
      $modification = null;
      if (($current=$position->getTakeprofit()) != ($previous=$position->getPrevTakeprofit())) $modification .= '  TakeProfit: '.($previous ? $previous.' => ':'').$current;
      if (($current=$position->getStopLoss())   != ($previous=$position->getPrevStopLoss())  ) $modification .= '  StopLoss: '  .($previous ? $previous.' => ':'').$current;
      if (!$modification) throw new plRuntimeException('No modification found in OpenPosition '.$position);

      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = 'position modified: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().$modification;
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      $mailMsg = $signal->getName().' Modify '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().$modification;
      foreach (self ::getMailSignalReceivers() as $receiver) {
         mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
      }


      // Benachrichtigung per SMS
      $smsMsg = 'Modify '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().$modification;
      foreach (self ::getSmsSignalReceivers() as $receiver) {
         sendSms($receiver, $signal, $smsMsg);
      }
   }


   /**
    * Handler für PositionClose-Events.
    *
    * @param  ClosedPosition $position - die geschlossene Position
    */
   public static function onPositionClose(ClosedPosition $position) {
      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = 'position closed: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().'  Open: '.$position->getOpenPrice().'  Close: '.$position->getClosePrice().'  Profit: '.$position->getProfit(2).'  ('.$position->getCloseTime('H:i:s').')';
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      $mailMsg = $signal->getName().' Close '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice();
      foreach (self ::getMailSignalReceivers() as $receiver) {
         mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
      }


      // Benachrichtigung per SMS
      $smsMsg = 'Close '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice();
      foreach (self ::getSmsSignalReceivers() as $receiver) {
         sendSms($receiver, $signal, $smsMsg);
      }
   }


   /**
    * Gibt die Mailadressen aller konfigurierten Signalempfänger per E-Mail zurück.
    *
    * @return string[] - Array mit E-Mailadressen
    */
   private static function getMailSignalReceivers() {
      static $addresses = null;

      if (is_null($addresses)) {
         $values = Config ::get('mail.myfx.signalreceivers');
         foreach (explode(',', $values) as $address) {
            if ($address=trim($address))
               $addresses[] = $address;
         }
         if (!$addresses)
            $addresses = array();
      }
      return $addresses;
   }


   /**
    * Gibt die Rufnummern aller konfigurierten Signalempfänger per SMS zurück.
    *
    * @return string[] - Array mit Rufnummern
    */
   private static function getSmsSignalReceivers() {
      static $numbers = null;

      if (is_null($numbers)) {
         $values = Config ::get('sms.myfx.signalreceivers', null);
         foreach (explode(',', $values) as $number) {
            if ($number=trim($number))
               $numbers[] = $number;
         }
         if (!$numbers)
            $numbers = array();
      }
      return $number;
   }
}
?>
