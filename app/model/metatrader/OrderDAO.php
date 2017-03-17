<?php
namespace rosasurfer\myfx\metatrader\model;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;

use const rosasurfer\db\orm\ID_CREATE;
use const rosasurfer\db\orm\ID_PRIMARY;
use const rosasurfer\db\orm\ID_VERSION;


/**
 * DAO for accessing {@link Order} instances.
 */
class OrderDAO extends DAO {


   /**
    * @var array - database mapping
    */
   protected $mapping = [
      'connection' => 'sqlite',
      'table'      => 't_order',
      'columns'    => [
         'id'          => ['id'           , PHP_TYPE_INT   , 0, ID_PRIMARY],      // db:int
         'created'     => ['created_utc'  , PHP_TYPE_STRING, 0, ID_CREATE ],      // db:text[datetime]
         'version'     => ['version_utc'  , PHP_TYPE_STRING, 0, ID_VERSION],      // db:text[datetime]

         'ticket'      => ['ticket'       , PHP_TYPE_INT   , 0, 0         ],      // db:int
         'type'        => ['type'         , PHP_TYPE_INT   , 0, 0         ],      // db:int
         'lots'        => ['lots'         , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,2)
         'symbol'      => ['symbol'       , PHP_TYPE_STRING, 0, 0         ],      // db:text(11)
         'openPrice'   => ['openprice'    , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,5)
         'openTime'    => ['opentime_fxt' , PHP_TYPE_STRING, 0, 0         ],      // db:text[datetime]
         'stopLoss'    => ['stoploss'     , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,5)
         'takeProfit'  => ['takeprofit'   , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,5)
         'closePrice'  => ['closeprice'   , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,5)
         'closeTime'   => ['closetime_fxt', PHP_TYPE_STRING, 0, 0         ],      // db:text[datetime]
         'commission'  => ['commission'   , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,2)
         'swap'        => ['swap'         , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,2)
         'profit'      => ['profit'       , PHP_TYPE_FLOAT , 0, 0         ],      // db:float(10,2)
         'magicNumber' => ['magicnumber'  , PHP_TYPE_INT   , 0, 0         ],      // db:int
         'comment'     => ['comment'      , PHP_TYPE_STRING, 0, 0         ],      // db:text(27)
         'test_id'     => ['test_id'      , PHP_TYPE_INT   , 0, 0         ],      // db:int
    ]];
}
