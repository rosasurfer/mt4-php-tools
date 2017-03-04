<?php
use rosasurfer\db\orm\PersistableObject;


echoPre(__FILE__.'  # line '.__LINE__);
/**
 * Signal
 */
class Signal extends PersistableObject {


   /** @var int - primary key */
   protected $id;

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
   public function getId()         { return $this->id;         }
   public function getProvider()   { return $this->provider;   }
   public function getProviderId() { return $this->providerId; }
   public function getName()       { return $this->name;       }
   public function getAlias()      { return $this->alias;      }
   public function getCurrency()   { return $this->currency;   }
}
