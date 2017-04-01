<?php
namespace rosasurfer\trade\model\metatrader;

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
            'modified'    => ['modified_utc' , PHP_TYPE_STRING, 0, ID_VERSION],      // db:text[datetime]

            'ticket'      => ['ticket'       , PHP_TYPE_INT   , 0, 0         ],      // db:int
            'type'        => ['type'         , PHP_TYPE_STRING, 0, 0         ],      // db:string[enum] references enum_ordertype(type)
            'lots'        => ['lots'         , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'symbol'      => ['symbol'       , PHP_TYPE_STRING, 0, 0         ],      // db:text
            'openPrice'   => ['openprice'    , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'openTime'    => ['opentime_fxt' , PHP_TYPE_STRING, 0, 0         ],      // db:text[datetime]
            'stopLoss'    => ['stoploss'     , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'takeProfit'  => ['takeprofit'   , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'closePrice'  => ['closeprice'   , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'closeTime'   => ['closetime_fxt', PHP_TYPE_STRING, 0, 0         ],      // db:text[datetime]
            'commission'  => ['commission'   , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'swap'        => ['swap'         , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'profit'      => ['profit'       , PHP_TYPE_FLOAT , 0, 0         ],      // db:float
            'magicNumber' => ['magicnumber'  , PHP_TYPE_INT   , 0, 0         ],      // db:int
            'comment'     => ['comment'      , PHP_TYPE_STRING, 0, 0         ],      // db:text
            'test_id'     => ['test_id'      , PHP_TYPE_INT   , 0, 0         ],      // db:int
     ]];
}
