<?php
/**
 * Account
 */
class Account extends PersistableObject {


   protected /*string*/ $company;
   protected /*int*/    $number;
   protected /*bool*/   $demo;
   protected /*string*/ $type;
   protected /*string*/ $timezone;
   protected /*string*/ $currency;
   protected /*float*/  $balance;
   protected /*string*/ $mtiAccount_id;


   // Getter
   public function getCompany()       { return         $this->company;       }
   public function getNumber()        { return         $this->number;        }
   public function isDemo()           { return  (bool) $this->demo;          }
   public function getType()          { return         $this->type;          }
   public function getTimezone()      { return         $this->timezone;      }
   public function getCurrency()      { return         $this->currency;      }
   public function getBalance()       { return (float) $this->balance;       }
   public function getMTiAccount_id() { return         $this->mtiAccount_id; }


   /**
    * Gibt den DAO dieser Klasse zurück.
    *
    * @return CommonDAO
    */
   public static function dao() {
      return self ::getDAO(__CLASS__);
   }


   /**
    * Normalisiert den übergebenen Account-Companynamen.
    *
    * @param  string $name - company name
    *
    * @return string - normalisierter Name
    *
    * Beispiel: Account::normalizeCompanyName('ATC Brokers - Main')  =>  'ATC Brokers'
    */
   public static function normalizeCompanyName($name) {
      switch (strToLower($name)) {
         case 'atc'                                :
         case 'atc brokers'                        :
         case 'atc brokers - main'                 :
         case 'atc brokers - $8 commission'        : return 'ATC Brokers';

         case 'alpari'                             : return 'Alpari';

         case 'alpari (uk)'                        :
         case 'alpari (uk) ltd'                    :
         case 'alpari (uk) ltd.'                   : return 'Alpari (UK)';

         case 'apbg'                               :
         case 'apbg trading'                       : return 'APBG Trading';

         case 'fb capital'                         :
         case 'fb capital ltd'                     :
         case 'fb capital ltd.'                    :
         case 'forex baltic'                       :
         case 'forex baltic ltd'                   :
         case 'forex baltic ltd.'                  :
         case 'forex baltic capital'               :
         case 'forex baltic capital ltd'           :
         case 'forex baltic capital ltd.'          : return 'FB Capital';

         case 'mb trading'                         :
         case 'mbt'                                :
         case 'mbtrading'                          : return 'MB Trading';

         case 'sig'                                :
         case 'sig, inc'                           :
         case 'sig, inc.'                          :
         case 'straighthold investment'            :
         case 'straighthold investment group'      :
         case 'straighthold investment group, inc' :
         case 'straighthold investment group, inc.': return 'Straighthold Investment';
      }
      return $name;
   }
}
?>
