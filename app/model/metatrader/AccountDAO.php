<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\IllegalTypeException;

use const rosasurfer\PHP_TYPE_BOOL;
use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;


/**
 * DAO zum Zugriff auf Account-Instanzen.
 */
class AccountDAO extends DAO {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_account',
      'columns'    => [
         'id'                  => ['id'                 , PHP_TYPE_INT   ],     // int
         'version'             => ['version'            , PHP_TYPE_STRING],     // timestamp
         'created'             => ['created'            , PHP_TYPE_STRING],     // datetime

         'company'             => ['company'            , PHP_TYPE_STRING],     // string
         'number'              => ['number'             , PHP_TYPE_STRING],     // string
         'demo'                => ['demo'               , PHP_TYPE_BOOL  ],     // tinyint
         'type'                => ['type'               , PHP_TYPE_STRING],     // enum
         'timezone'            => ['timezone'           , PHP_TYPE_STRING],     // string
         'currency'            => ['currency'           , PHP_TYPE_STRING],     // string
         'balance'             => ['balance'            , PHP_TYPE_FLOAT ],     // decimal
         'lastReportedBalance' => ['lastreportedbalance', PHP_TYPE_FLOAT ],     // decimal
         'lastUpdate'          => ['lastupdate'         , PHP_TYPE_STRING],     // datetime
         'mtiAccountId'        => ['mtiaccount_id'      , PHP_TYPE_STRING],     // string
   ]];


   /**
    * Gibt einen einzelnen Account zurueck.
    *
    * @param  string $company - company name
    * @param  string $number  - account number
    *
    * @return Account
    */
   public function getByCompanyAndNumber($company, $number) {
      if (!is_string($company)) throw new IllegalTypeException('Illegal type of parameter $company: '.getType($company));
      if (!is_string($number))  throw new IllegalTypeException('Illegal type of parameter $number: '.getType($number));

      $company = $this->escapeString($company);
      $number  = $this->escapeString($number);

      $sql = "select *
                 from t_account
                 where company = '$company'
                   and number = '$number'";
      return $this->findOne($sql);
   }
}
