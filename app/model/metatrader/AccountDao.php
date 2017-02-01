<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\dao\CommonDao;
use rosasurfer\exception\IllegalTypeException;


/**
 * DAO zum Zugriff auf Account-Instanzen.
 */
class AccountDao extends CommonDao {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_account',
      'fields'     => [
         'id'                  => ['id'                 , self::T_INT   , self::T_NOT_NULL],     // int
         'version'             => ['version'            , self::T_STRING, self::T_NOT_NULL],     // timestamp
         'created'             => ['created'            , self::T_STRING, self::T_NOT_NULL],     // datetime

         'company'             => ['company'            , self::T_STRING, self::T_NOT_NULL],     // string
         'number'              => ['number'             , self::T_STRING, self::T_NOT_NULL],     // string
         'demo'                => ['demo'               , self::T_BOOL  , self::T_NOT_NULL],     // tinyint
         'type'                => ['type'               , self::T_STRING, self::T_NOT_NULL],     // enum
         'timezone'            => ['timezone'           , self::T_STRING, self::T_NOT_NULL],     // string
         'currency'            => ['currency'           , self::T_STRING, self::T_NOT_NULL],     // string
         'balance'             => ['balance'            , self::T_FLOAT , self::T_NOT_NULL],     // decimal
         'lastReportedBalance' => ['lastreportedbalance', self::T_FLOAT , self::T_NULL    ],     // decimal
         'lastUpdate'          => ['lastupdate'         , self::T_STRING, self::T_NULL    ],     // datetime
         'mtiAccountId'        => ['mtiaccount_id'      , self::T_STRING, self::T_NULL    ],     // string
   ]];


   /**
    * Gibt einen einzelnen Account zurück.
    *
    * @param  string $company - company name
    * @param  string $number  - account number
    *
    * @return Account
    */
   public function getByCompanyAndNumber($company, $number) {
      if (!is_string($company)) throw new IllegalTypeException('Illegal type of parameter $company: '.getType($company));
      if (!is_string($number))  throw new IllegalTypeException('Illegal type of parameter $number: '.getType($number));

      $company = addSlashes($company);
      $number  = addSlashes($number);

      $sql = "select *
                 from t_account
                 where company = '$company'
                   and number = '$number'";
      return $this->fetchOne($sql);
   }
}
