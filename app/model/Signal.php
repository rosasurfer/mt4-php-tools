<?php
use rosasurfer\db\orm\PersistableObject;


/**
 * Signal
 */
class Signal extends PersistableObject {


   /** @var string */
   protected $provider;

   /** @var string */
   protected $providerId;

   /** @var string */
   protected $name;

   /** @var string */
   protected $alias;

   /** @var string */
   protected $currency;


   // Simple getters
   public function getProvider()   { return $this->provider;   }
   public function getProviderId() { return $this->providerId; }
   public function getName()       { return $this->name;       }
   public function getAlias()      { return $this->alias;      }
   public function getCurrency()   { return $this->currency;   }
}
