<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\DAO;
use rosasurfer\ministruts\db\orm\ORM;

/**
 * DAO for accessing {@link Order} instances.
 *
 * @phpstan-import-type ORM_ENTITY from \rosasurfer\ministruts\phpstan\UserTypes
 */
class OrderDAO extends DAO {

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     * @phpstan-return ORM_ENTITY
     */
    public function getMapping(): array {
        static $mapping;
        return $mapping ??= $this->parseMapping([
            'class'      => Order::class,
            'connection' => 'rosatrader',
            'table'      => 't_order',
            'properties' => [
                ['name'=>'id',          'type'=>ORM::INT,    'primary-key'=>true],                  // db:int
                ['name'=>'created',     'type'=>ORM::STRING,                    ],                  // db:text[datetime] GMT
                ['name'=>'modified',    'type'=>ORM::STRING, 'version'=>true    ],                  // db:text[datetime] GMT

                ['name'=>'ticket',      'type'=>ORM::INT,                       ],                  // db:int
                ['name'=>'type',        'type'=>ORM::STRING,                    ],                  // db:string[enum] references enum_ordertype(type)
                ['name'=>'lots',        'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'symbol',      'type'=>ORM::STRING,                    ],                  // db:text
                ['name'=>'openPrice',   'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'openTime',    'type'=>ORM::STRING,                    ],                  // db:text[datetime] FXT
                ['name'=>'stopLoss',    'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'takeProfit',  'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'closePrice',  'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'closeTime',   'type'=>ORM::STRING,                    ],                  // db:text[datetime] FXT
                ['name'=>'commission',  'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'swap',        'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'profit',      'type'=>ORM::FLOAT,                     ],                  // db:float
                ['name'=>'magicNumber', 'type'=>ORM::INT,                       ],                  // db:int
                ['name'=>'comment',     'type'=>ORM::STRING,                    ],                  // db:text
            ],
            'relations' => [
                ['name'=>'test', 'type'=>'many-to-one', 'class'=>Test::class, 'column'=>'test_id'], // db:int
            ],
        ]);
    }
}
