<?
/**
 * OpenPosition
 */
class OpenPosition extends PersistableObject {


   protected /*int*/    $ticket;
   protected /*string*/ $type;
   protected /*float*/  $lots;
   protected /*string*/ $symbol;
   protected /*string*/ $openTime;
   protected /*float*/  $openPrice;
   protected /*float*/  $stopLoss;
   protected /*float*/  $takeProfit;
   protected /*float*/  $commission;
   protected /*float*/  $swap;
   protected /*int*/    $magicNumber;
   protected /*string*/ $comment;
   protected /*int*/    $signal_id;


   /**
    * Gibt den DAO für diese Klasse zurück.
    *
    * @return CommonDAO
    */
   public static function dao() {
      return self ::getDAO(__CLASS__);
   }
}
?>
