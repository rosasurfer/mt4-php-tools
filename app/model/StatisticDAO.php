<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\DAO;
use rosasurfer\ministruts\db\orm\ORM;

/**
 * DAO for accessing {@link Statistic} instances.
 *
 * @phpstan-import-type ORM_ENTITY from \rosasurfer\ministruts\phpstan\UserTypes
 */
class StatisticDAO extends DAO {

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     * @phpstan-return ORM_ENTITY
     */
    public function getMapping(): array {
        static $mapping;
        return $mapping ??= $this->parseMapping([
            'class'      => Statistic::class,
            'connection' => 'rosatrader',
            'table'      => 't_statistic',
            'properties' => [
                ['name'=>'id',              'type'=>ORM::INT,   'primary-key'=>true         ],      // db:int
                ['name'=>'trades',          'type'=>ORM::INT,                               ],      // db:int
                ['name'=>'tradesPerDay',    'type'=>ORM::FLOAT, 'column'=>'trades_day'      ],      // db:float
                ['name'=>'minDuration',     'type'=>ORM::INT,   'column'=>'duration_min'    ],      // db:int
                ['name'=>'avgDuration',     'type'=>ORM::INT,   'column'=>'duration_avg'    ],      // db:int
                ['name'=>'maxDuration',     'type'=>ORM::INT,   'column'=>'duration_max'    ],      // db:int
                ['name'=>'minPips',         'type'=>ORM::FLOAT, 'column'=>'pips_min'        ],      // db:float
                ['name'=>'avgPips',         'type'=>ORM::FLOAT, 'column'=>'pips_avg'        ],      // db:float
                ['name'=>'maxPips',         'type'=>ORM::FLOAT, 'column'=>'pips_max'        ],      // db:float
                ['name'=>'pips',            'type'=>ORM::FLOAT,                             ],      // db:float
                ['name'=>'sharpeRatio',     'type'=>ORM::FLOAT, 'column'=>'sharpe_ratio'    ],      // db:float
                ['name'=>'sortinoRatio',    'type'=>ORM::FLOAT, 'column'=>'sortino_ratio'   ],      // db:float
                ['name'=>'calmarRatio',     'type'=>ORM::FLOAT, 'column'=>'calmar_ratio'    ],      // db:float
                ['name'=>'maxRecoveryTime', 'type'=>ORM::INT,   'column'=>'max_recoverytime'],      // db:int
                ['name'=>'grossProfit',     'type'=>ORM::FLOAT, 'column'=>'gross_profit'    ],      // db:float
                ['name'=>'commission',      'type'=>ORM::FLOAT,                             ],      // db:float
                ['name'=>'swap',            'type'=>ORM::FLOAT,                             ],      // db:float
            ],
            'relations' => [
                ['name'=>'test', 'type'=>'one-to-one', 'class'=>Test::class, 'column'=>'test_id'],  // db:int
            ],
        ]);
    }
}
