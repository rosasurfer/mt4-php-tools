<?
/**
 * Validator
 */
class Validator extends CommonValidator {


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
}
?>
