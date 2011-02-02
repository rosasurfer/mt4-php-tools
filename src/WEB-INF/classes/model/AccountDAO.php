<?php
/**
 * DAO zum Zugriff auf Account-Instanzen.
 */
class AccountDAO extends CommonDAO {


   // Datenbankmapping
   public $mapping = array('link'   => 'myfx',
                           'table'  => 't_account',
                           'fields' => array('id'           => array('id'           , self ::T_INT   , self ::T_NOT_NULL),     // int
                                             'version'      => array('version'      , self ::T_STRING, self ::T_NOT_NULL),     // timestamp
                                             'created'      => array('created'      , self ::T_STRING, self ::T_NOT_NULL),     // datetime

                                             'company'      => array('company'      , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'number'       => array('number'       , self ::T_INT   , self ::T_NOT_NULL),     // int
                                             'demo'         => array('demo'         , self ::T_BOOL  , self ::T_NOT_NULL),     // tinyint
                                             'type'         => array('type'         , self ::T_STRING, self ::T_NOT_NULL),     // enum
                                             'timezone'     => array('timezone'     , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'currency'     => array('currency'     , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'balance'      => array('balance'      , self ::T_FLOAT , self ::T_NOT_NULL),     // decimal
                                             'lastUpdated'  => array('lastupdated'  , self ::T_STRING, self ::T_NULL    ),     // datetime
                                             'mtiAccountId' => array('mtiaccount_id', self ::T_STRING, self ::T_NULL    ),     // string
                                            ));

   /**
    * Gibt einen einzelnen Account zurück.
    *
    * @param  string $company - company name
    * @param  int    $number  - account number
    *
    * @return Account instance
    */
   public function getByCompanyAndNumber($company, $number) {
      if (!is_string($company)) throw new IllegalTypeException('Illegal type of parameter $company: '.getType($company));
      if (!is_int($number))     throw new IllegalTypeException('Illegal type of parameter $number: '.getType($number));

      $company = addSlashes($company);

      $sql = "select *
                 from t_account
                 where company = '$company'
                   and number = $number";
      return $this->getByQuery($sql);
   }
}
?>
