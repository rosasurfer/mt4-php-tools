<?php
/**
 * HistoryHeader eines HistoryFiles ("*.hst")
 */
class HistoryHeader extends Object {

   /**
    * Struct-Size eines HistoryHeaders
    */
   const STRUCT_SIZE = 148;


   /**
    * Formatbeschreibung eines struct HISTORY_HEADER.
    *
    * @see  Definition in MT4Expander.dll::Expander.h
    * @see  self::unpackFormat() zum Verwenden als unpack()-Formatstring
    */
   private static $format = '
      /V   format
      /a64 copyright
      /a12 symbol
      /V   period
      /V   digits
      /V   syncMark
      /V   lastSync
      /x52 reserved
   ';


   /**
    * Gibt den Formatstring zum Entpacken eines struct HISTORY_HEADER zurück.
    *
    * @return string - unpack()-Formatstring
    */
   public static function unpackFormat() {
      static $format = null;

      if (is_null($format)) {
         $format = self::$format;

         // since PHP 5.5.0: The 'a' code now retains trailing NULL bytes, 'Z' replaces the former 'a'.
         if (PHP_VERSION >= '5.5.0') $format = str_replace('/a', '/Z', $format);

         // remove white space and leading format separator
         $format = preg_replace('/\s/', '', $format);
         if ($format[0] == '/') $format = substr($format, 1);
      }
      return $format;
   }
}
