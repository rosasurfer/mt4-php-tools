<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\DAO;

use const rosasurfer\ministruts\db\orm\meta\FLOAT;
use const rosasurfer\ministruts\db\orm\meta\INT;


/**
 * DAO for accessing {@link Statistic} instances.
 */
class StatisticDAO extends DAO {


    /**
     * {@inheritdoc}
     *
     * @return array<string, string|array<scalar[]>>
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_statistic',
            'class'      => Statistic::class,
            'properties' => [
                ['name'=>'id',              'type'=>INT,   'primary'=>true             ],           // db:int
                ['name'=>'trades',          'type'=>INT,                               ],           // db:int
                ['name'=>'tradesPerDay',    'type'=>FLOAT, 'column'=>'trades_day'      ],           // db:float
                ['name'=>'minDuration',     'type'=>INT,   'column'=>'duration_min'    ],           // db:int
                ['name'=>'avgDuration',     'type'=>INT,   'column'=>'duration_avg'    ],           // db:int
                ['name'=>'maxDuration',     'type'=>INT,   'column'=>'duration_max'    ],           // db:int
                ['name'=>'minPips',         'type'=>FLOAT, 'column'=>'pips_min'        ],           // db:float
                ['name'=>'avgPips',         'type'=>FLOAT, 'column'=>'pips_avg'        ],           // db:float
                ['name'=>'maxPips',         'type'=>FLOAT, 'column'=>'pips_max'        ],           // db:float
                ['name'=>'pips',            'type'=>FLOAT,                             ],           // db:float
                ['name'=>'sharpeRatio',     'type'=>FLOAT, 'column'=>'sharpe_ratio'    ],           // db:float
                ['name'=>'sortinoRatio',    'type'=>FLOAT, 'column'=>'sortino_ratio'   ],           // db:float
                ['name'=>'calmarRatio',     'type'=>FLOAT, 'column'=>'calmar_ratio'    ],           // db:float
                ['name'=>'maxRecoveryTime', 'type'=>INT,   'column'=>'max_recoverytime'],           // db:int
                ['name'=>'grossProfit',     'type'=>FLOAT, 'column'=>'gross_profit'    ],           // db:float
                ['name'=>'commission',      'type'=>FLOAT,                             ],           // db:float
                ['name'=>'swap',            'type'=>FLOAT,                             ],           // db:float
            ],
            'relations' => [
                ['name'=>'test', 'assoc'=>'one-to-one', 'type'=>Test::class, 'column'=>'test_id'],  // db:int
            ],
        ]));
    }
}
