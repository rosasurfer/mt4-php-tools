<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;


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
            'id'          => ['column'=>'id'         , 'type'=>PHP_TYPE_INT   , 'primary'=>true],      // db:int
            'created'     => ['column'=>'created'    , 'type'=>PHP_TYPE_STRING,                ],      // db:text[datetime] GMT
            'modified'    => ['column'=>'modified'   , 'type'=>PHP_TYPE_STRING, 'version'=>true],      // db:text[datetime] GMT

            'ticket'      => ['column'=>'ticket'     , 'type'=>PHP_TYPE_INT   ,                ],      // db:int
            'type'        => ['column'=>'type'       , 'type'=>PHP_TYPE_STRING,                ],      // db:string[enum] references enum_ordertype(type)
            'lots'        => ['column'=>'lots'       , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'symbol'      => ['column'=>'symbol'     , 'type'=>PHP_TYPE_STRING,                ],      // db:text
            'openPrice'   => ['column'=>'openprice'  , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'openTime'    => ['column'=>'opentime'   , 'type'=>PHP_TYPE_STRING,                ],      // db:text[datetime] FXT
            'stopLoss'    => ['column'=>'stoploss'   , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'takeProfit'  => ['column'=>'takeprofit' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'closePrice'  => ['column'=>'closeprice' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'closeTime'   => ['column'=>'closetime'  , 'type'=>PHP_TYPE_STRING,                ],      // db:text[datetime] FXT
            'commission'  => ['column'=>'commission' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'swap'        => ['column'=>'swap'       , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'profit'      => ['column'=>'profit'     , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
            'magicNumber' => ['column'=>'magicnumber', 'type'=>PHP_TYPE_INT   ,                ],      // db:int
            'comment'     => ['column'=>'comment'    , 'type'=>PHP_TYPE_STRING,                ],      // db:text
            'test_id'     => ['column'=>'test_id'    , 'type'=>PHP_TYPE_INT   ,                ],      // db:int
        ],
        'relations' => [
            'test' => ['relation'=>'many-to-one', 'name'=>'test', 'column'=>'test_id', 'type'=>Test::class],
        ],
    ];
}
