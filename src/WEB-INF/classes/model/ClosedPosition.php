<?
/**
 * ClosedPosition
 */
class ClosedPosition extends PersistableObject {


   protected /*int*/    $ticket;
   protected /*string*/ $type;
   protected /*float*/  $lots;
   protected /*string*/ $symbol;
   protected /*string*/ $openTime;
   protected /*float*/  $openPrice;
   protected /*string*/ $closeTime;
   protected /*float*/  $closePrice;
   protected /*float*/  $stopLoss;
   protected /*float*/  $takeProfit;
   protected /*float*/  $commission;
   protected /*float*/  $swap;
   protected /*float*/  $profit;
   protected /*int*/    $magicNumber;
   protected /*string*/ $comment;
   protected /*int*/    $signal_id;

   private   /*Signal*/ $signal;


   // Getter
   public function getTicket()      { return $this->ticket;      }
   public function getType()        { return $this->type;        }
   public function getLots()        { return $this->lots;        }
   public function getSymbol()      { return $this->symbol;      }
   public function getOpenTime()    { return $this->openTime;    }
   public function getOpenPrice()   { return $this->openPrice;   }
   public function getCloseTime()   { return $this->closeTime;   }
   public function getClosePrice()  { return $this->closePrice;  }
   public function getStopLoss()    { return $this->stopLoss;    }
   public function getTakeProfit()  { return $this->takeProfit;  }
   public function getCommission()  { return $this->commission;  }
   public function getSwap()        { return $this->swap;        }
   public function getProfit()      { return $this->profit;      }
   public function getMagicNumber() { return $this->magicNumber; }
   public function getComment()     { return $this->comment;     }
   public function getSignal_id()   { return $this->signal_id;   }


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
