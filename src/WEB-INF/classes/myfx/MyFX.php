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
      echoPre($message='position opened: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().'  TP: '.ifNull($position->getTakeProfit(),'-').'  SL: '.ifNull($position->getStopLoss(), '-').'  ('.$position->getOpenTime('H:i:s').')');

      $signal = $position->getSignal()->getName();

      // Mail verschicken
      $sender   = 'default@domain.tld';
      $receiver = Config ::get('mail.myfx.signalreceivers');
      $subject  = $signal.' Opened '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol();
      mail($receiver, $subject, $message);
   }


   /**
    * Handler für PositionModify-Events.
    *
    * @param  OpenPosition $position - die modifizierte Position
    */
   public static function onPositionModify(OpenPosition $position) {
      $msg = null;
      if (($current=$position->getTakeprofit()) != ($previous=$position->getPrevTakeprofit())) $msg .= '  TakeProfit: '.($previous ? $previous.' => ':'').$current;
      if (($current=$position->getStopLoss())   != ($previous=$position->getPrevStopLoss())  ) $msg .= '  StopLoss: '  .($previous ? $previous.' => ':'').$current;

      echoPre($message='position modified: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().$msg);

      $signal = $position->getSignal()->getName();

      // Mail verschicken
      $sender   = 'default@domain.tld';
      $receiver = Config ::get('mail.myfx.signalreceivers');
      $subject  = $signal.' Modified '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol();
      mail($receiver, $subject, $message);

      /*
      // Mail per SMTP-Mailer verschicken
      $receiver = Config ::get('mail.myfx.signalreceivers');
      $subject  = "DayFox signal: Position Modify via Mailer class";
      $message  = "test message body";
      $headers  = array("Content-Type: text/plain; charset=iso-8859-1");
      try {
         $options = Config ::me()->get('mail.myfx');
         Mailer ::create($options)->sendMail($sender, $receiver, $subject, $message, $headers);
      }
      catch (Exception $ex) {
         throw new InfrastructureException('Error sending mail to: '.$receiver, $ex);
      }
      */
   }


   /**
    * Handler für PositionClose-Events.
    *
    * @param  ClosedPosition $position - die geschlossene Position
    */
   public static function onPositionClose(ClosedPosition $position) {
      echoPre($message='position closed: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().'  Open: '.$position->getOpenPrice().'  Close: '.$position->getClosePrice().'  Profit: '.$position->getProfit(2).'  ('.$position->getCloseTime('H:i:s').')');

      $signal = $position->getSignal()->getName();

      // Mail verschicken
      $sender   = 'default@domain.tld';
      $receiver = Config ::get('mail.myfx.signalreceivers');
      $subject  = $signal.' Closed '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol();
      mail($receiver, $subject, $message);
   }


   /**
    * Verschickt eine Nachricht über eine neue offene Position.
    *
    * @param  OpenPosition $position - die geöffnete Position
    */
   public static function sendSignalOpenMessage(OpenPosition $position) {
   }


   /**
    * Verschickt eine Nachricht über eine modifizierte Position.
    *
    * @param  OpenPosition $position - die modifizierte Position
    */
   public static function sendSignalModifyMessage(OpenPosition $position) {
   }


   /**
    * Verschickt eine Nachricht über eine geschlossene Position.
    *
    * @param  ClosedPosition $position - die geschlossene Position
    */
   public static function sendSignalCloseMessage(ClosedPosition $position) {
   }
}
?>
