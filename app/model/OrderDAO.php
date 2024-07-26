<?php
namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\DAO;

use const rosasurfer\ministruts\db\orm\meta\FLOAT;
use const rosasurfer\ministruts\db\orm\meta\INT;
use const rosasurfer\ministruts\db\orm\meta\STRING;


/**
 * DAO for accessing {@link Order} instances.
 */
class OrderDAO extends DAO {


    /**
     *
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_order',
            'class'      => Order::class,
            'properties' => [
                ['name'=>'id',          'type'=>INT,    'primary'=>true],                           // db:int
                ['name'=>'created',     'type'=>STRING,                ],                           // db:text[datetime] GMT
                ['name'=>'modified',    'type'=>STRING, 'version'=>true],                           // db:text[datetime] GMT

                ['name'=>'ticket',      'type'=>INT,                   ],                           // db:int
                ['name'=>'type',        'type'=>STRING,                ],                           // db:string[enum] references enum_ordertype(type)
                ['name'=>'lots',        'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'symbol',      'type'=>STRING,                ],                           // db:text
                ['name'=>'openPrice',   'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'openTime',    'type'=>STRING,                ],                           // db:text[datetime] FXT
                ['name'=>'stopLoss',    'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'takeProfit',  'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'closePrice',  'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'closeTime',   'type'=>STRING,                ],                           // db:text[datetime] FXT
                ['name'=>'commission',  'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'swap',        'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'profit',      'type'=>FLOAT,                 ],                           // db:float
                ['name'=>'magicNumber', 'type'=>INT,                   ],                           // db:int
                ['name'=>'comment',     'type'=>STRING,                ],                           // db:text
            ],
            'relations' => [
                ['name'=>'test', 'assoc'=>'many-to-one', 'type'=>Test::class, 'column'=>'test_id'], // db:int
            ],
        ]));
    }
}
