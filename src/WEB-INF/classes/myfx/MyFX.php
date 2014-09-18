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
    * @param int $timestamp - Zeitpunkt
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
}
?>
