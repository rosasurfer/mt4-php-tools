<?php
/**
 * Signal
 */
class Signal extends PersistableObject {


   protected /*string*/ $name;
   protected /*string*/ $alias;
   protected /*string*/ $referenceID;
   protected /*string*/ $currency;


   // Getter
   public function getName()        { return $this->name;        }
   public function getAlias()       { return $this->alias;       }
   public function getReferenceID() { return $this->referenceID; }
   public function getCurrency()    { return $this->currency;    }
}
