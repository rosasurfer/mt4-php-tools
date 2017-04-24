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
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'class'      => Order::class,
            'table'      => 't_order',
            'connection' => 'sqlite',
            'properties' => [
                ['name'=>'id'         , 'type'=>PHP_TYPE_INT   , 'primary'=>true],      // db:int
                ['name'=>'created'    , 'type'=>PHP_TYPE_STRING,                ],      // db:text[datetime] GMT
                ['name'=>'modified'   , 'type'=>PHP_TYPE_STRING, 'version'=>true],      // db:text[datetime] GMT

                ['name'=>'ticket'     , 'type'=>PHP_TYPE_INT   ,                ],      // db:int
                ['name'=>'type'       , 'type'=>PHP_TYPE_STRING,                ],      // db:string[enum] references enum_ordertype(type)
                ['name'=>'lots'       , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'symbol'     , 'type'=>PHP_TYPE_STRING,                ],      // db:text
                ['name'=>'openPrice'  , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'openTime'   , 'type'=>PHP_TYPE_STRING,                ],      // db:text[datetime] FXT
                ['name'=>'stopLoss'   , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'takeProfit' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'closePrice' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'closeTime'  , 'type'=>PHP_TYPE_STRING,                ],      // db:text[datetime] FXT
                ['name'=>'commission' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'swap'       , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'profit'     , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:float
                ['name'=>'magicNumber', 'type'=>PHP_TYPE_INT   ,                ],      // db:int
                ['name'=>'comment'    , 'type'=>PHP_TYPE_STRING,                ],      // db:text
                ['name'=>'test_id'    , 'type'=>PHP_TYPE_INT   ,                ],      // db:int
            ],
            'relations' => [
                ['name'=>'test', 'relation'=>'many-to-one', 'type'=>Test::class, 'column'=>'test_id'],
            ],
        ]));
    }
}
