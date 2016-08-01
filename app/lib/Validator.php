<?php
/**
 * Validator
 */
class Validator extends rosasurfer\util\Validator {


   /**
    * Ob der übergebene Wert ein gültiger OperationType-Identifier ist.
    *
    * @param  int $type - zu prüfender Wert
    *
    * @return bool
    */
   public static function isOperationType($type) {
      return (is_int($type) && isSet(ViewHelper ::$operationTypes[$type]));
   }


   /**
    * Ob der übergebene Wert ein gültiger MT4-OperationType-Identifier ist.
    *
    * @param  int $type - zu prüfender Wert
    *
    * @return bool
    */
   public static function isMT4OperationType($type) {
      return (self:: isOperationType($type) && $type <= OP_CREDIT);
   }


   /**
    * Ob der übergebene Wert ein gültiger Custom-OperationType-Identifier ist.
    *
    * @param  int $type - zu prüfender Wert
    *
    * @return bool
    */
   public static function isCustomOperationType($type) {
      return (self:: isOperationType($type) && $type > OP_CREDIT);
   }


   /**
    * Ob der übergebene String ein gültiger Instrumentbezeichner ist.
    *
    * @param  string $string - zu prüfender Sring
    *
    * @return bool
    */
   public static function isInstrument($string) {
      return (is_string($string) && isSet(ViewHelper ::$instruments[$string]));
   }
}
