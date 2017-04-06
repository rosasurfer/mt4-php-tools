<?php
namespace rosasurfer\trade\model\metatrader;

use rosasurfer\db\orm\PersistableObject;
use rosasurfer\exception\IllegalTypeException;

use rosasurfer\util\Date;
use rosasurfer\util\Number;


/**
 * Account
 */
class Account extends PersistableObject {


    /** @var int - primary key */
    protected $id;

    /** @var string - time of creation */
    protected $created;

    /** @var string - time of last modification */
    protected $version;

    /** @var string */
    protected $company;

    /** @var string */
    protected $number;

    /** @var bool */
    protected $demo;

    /** @var bool   */
    protected $type;

    /** @var string */
    protected $timezone;

    /** @var string */
    protected $currency;

    /** @var float */
    protected $balance;

    /** @var float */
    protected $lastReportedBalance;

    /** @var string */
    protected $lastUpdate;

    /** @var string */
    protected $mtiAccountId;


    // Getter
    public function getId()           { return        $this->id;           }
    public function getCompany()      { return        $this->company;      }
    public function getNumber()       { return        $this->number;       }
    public function isDemo()          { return (bool) $this->demo;         }
    public function getType()         { return        $this->type;         }
    public function getTimezone()     { return        $this->timezone;     }
    public function getCurrency()     { return        $this->currency;     }
    public function getMTiAccountId() { return        $this->mtiAccountId; }


    /**
     * Return the creation time of the instance.
     *
     * @param  string $format - format as used by date($format, $timestamp)
     *
     * @return string - creation time
     */
    public function getCreated($format = 'Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->created;
        return Date::format($this->created, $format);
    }


    /**
     * Return the version string of the instance.
     *
     * @param  string $format - format as used by date($format, $timestamp)
     *
     * @return string - version (last modification time)
     */
    public function getVersion($format = 'Y-m-d H:i:s')  {
        if ($format == 'Y-m-d H:i:s')
            return $this->version;
        return Date::format($this->version, $format);
    }


    /**
     * Gibt den aktuellen Kontostand des Accounts zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return float|string - Kontostand
     */
    public function getBalance($decimals = 2, $separator = ',') {
        if (func_num_args() == 0)
            return $this->balance;

        return Number::formatMoney($this->balance, $decimals, $separator);
    }


    /**
     * Gibt den letzten gemeldeten Kontostand des Accounts zurueck.
     *
     * @param  int    $decimals  - Anzahl der Nachkommastellen
     * @param  string $separator - Dezimaltrennzeichen
     *
     * @return float|string|null - Kontostand
     */
    public function getLastReportedBalance($decimals = 2, $separator = ',') {
        if (is_null($this->lastReportedBalance) || func_num_args()==0)
            return $this->lastReportedBalance;

        return Number::formatMoney($this->lastReportedBalance, $decimals, $separator);
    }


    /**
     * Setzt den letzten gemeldeten Kontostand des Accounts.
     *
     * @param  float $balance - Kontostand
     *
     * @return $this
     */
    public function setLastReportedBalance($balance) {
        if (!is_float($balance)) throw new IllegalTypeException('Illegal type of parameter $balance: '.getType($balance));

        if ($this->lastReportedBalance === $balance)
            return $this;

        $this->lastReportedBalance = $balance;

        $this->isPersistent() && $this->_modified=true;
        return $this;
    }


    /**
     * Gibt den Zeitpunkt des letzten History-Updates zurueck.
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
     * Normalisiert den uebergebenen Account-Companynamen.
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
