<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\db\orm\PersistableObject;

use rosasurfer\exception\ConcurrentModificationException;
use rosasurfer\exception\IllegalTypeException;

use rosasurfer\util\Date;
use rosasurfer\util\Number;


/**
 * Account
 */
class Account extends PersistableObject {


   protected /*string*/ $company;
   protected /*string*/ $number;
   protected /*bool  */ $demo;
   protected /*string*/ $type;
   protected /*string*/ $timezone;
   protected /*string*/ $currency;
   protected /*float */ $balance;
   protected /*float */ $lastReportedBalance;
   protected /*string*/ $lastUpdate;
   protected /*string*/ $mtiAccountId;


   // Getter
   public function getCompany()      { return        $this->company;      }
   public function getNumber()       { return        $this->number;       }
   public function isDemo()          { return (bool) $this->demo;         }
   public function getType()         { return        $this->type;         }
   public function getTimezone()     { return        $this->timezone;     }
   public function getCurrency()     { return        $this->currency;     }
   public function getMTiAccountId() { return        $this->mtiAccountId; }


   /**
    * Gibt den aktuellen Kontostand des Accounts zurück.
    *
    * @param  int    $decimals  - Anzahl der Nachkommastellen
    * @param  string $separator - Dezimaltrennzeichen
    *
    * @return float|string - Kontostand
    */
   public function getBalance($decimals = 2, $separator = ',') {
      if (func_num_args() == 0)
         return $this->balance;

      return Number::format($this->balance, $decimals, $separator);
   }


   /**
    * Gibt den letzten gemeldeten Kontostand des Accounts zurück.
    *
    * @param  int    $decimals  - Anzahl der Nachkommastellen
    * @param  string $separator - Dezimaltrennzeichen
    *
    * @return float|string - Kontostand
    */
   public function getLastReportedBalance($decimals = 2, $separator = ',') {
      if (is_null($this->lastReportedBalance) || func_num_args()==0)
         return $this->lastReportedBalance;

      return Number::format($this->lastReportedBalance, $decimals, $separator);
   }


   /**
    * Setzt den letzten gemeldeten Kontostand des Accounts.
    *
    * @param  float $balance - Kontostand
    *
    * @return self
    */
   public function setLastReportedBalance($balance) {
      if (!is_float($balance)) throw new IllegalTypeException('Illegal type of parameter $balance: '.getType($balance));

      if ($this->lastReportedBalance === $balance)
         return $this;

      $this->lastReportedBalance = $balance;

      $this->isPersistent() && $this->modified=true;
      return $this;
   }


   /**
    * Gibt den Zeitpunkt des letzten History-Updates zurück.
    *
    * @param  string $format - Zeitformat
    *
    * @return string - Zeitpunkt oder NULL, wenn noch kein Update erfolgte
    */
   public function getLastUpdate($format = 'Y-m-d H:i:s')  {
      if (!$this->lastUpdate || $format=='Y-m-d H:i:s')
         return $this->lastUpdate;

      return Date::format($this->lastUpdate, $format);
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


   /**
    * Aktualisiert diese Instanz in der Datenbank.
    *
    * @return Invoice
    */
   protected function update() {
      $db = self::db();

      $id                  = $this->id;
      $oldVersion          = $this->version;
      $newVersion          = $this->touch();

      $lastreportedbalance = ($this->lastReportedBalance === null) ? 'null' : $this->lastReportedBalance;
      $mtiaccount_id       = ($this->mtiAccountId        === null) ? 'null' : "'".$db->escapeString($this->mtiAccountId)."'";

      $db->begin();
      try {
         // Account updaten
         $sql = "update t_account
                    set lastreportedbalance = $lastreportedbalance,
                        mtiaccount_id       = $mtiaccount_id,
                        version             = '$newVersion'
                    where id = $id
                      and version = '$oldVersion'";
         if ($db->execute($sql)->lastAffectedRows() != 1) {
            $this->version = $oldVersion;
            $found = self::dao()->refresh($this);
            throw new ConcurrentModificationException('Error updating '.__CLASS__." ($id), expected version: \"$oldVersion\", found version: \"".$found->getVersion().'"');
         }

         // alles speichern
         $db->commit();

         $this->modifications = null;
      }
      catch (\Exception $ex) {
         $db->rollback();
         throw $ex;
      }
      return $this;
   }
}
