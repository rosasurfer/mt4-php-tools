<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\db\orm\DAO;
use rosasurfer\ministruts\db\orm\ORM;


/**
 * DAO for accessing {@link Test} instances.
 *
 * @phpstan-import-type  ORM_ENTITY from \rosasurfer\ministruts\db\orm\ORM
 */
class TestDAO extends DAO {

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     * @phpstan-return ORM_ENTITY
     */
    public function getMapping(): array {
        static $mapping;
        return $mapping ??= $this->parseMapping([
            'class'      => Test::class,
            'connection' => 'rosatrader',
            'table'      => 't_test',
            'properties' => [
                ['name'=>'id',              'type'=>ORM::INT,    'primary-key'=>true],      // db:int
                ['name'=>'created',         'type'=>ORM::STRING,                    ],      // db:text[datetime] GMT
                ['name'=>'modified',        'type'=>ORM::STRING, 'version'=>true    ],      // db:text[datetime] GMT

                ['name'=>'strategy',        'type'=>ORM::STRING,                    ],      // db:text
                ['name'=>'reportingId',     'type'=>ORM::INT,                       ],      // db:int
                ['name'=>'reportingSymbol', 'type'=>ORM::STRING,                    ],      // db:text
                ['name'=>'symbol',          'type'=>ORM::STRING,                    ],      // db:text
                ['name'=>'timeframe',       'type'=>ORM::INT,                       ],      // db:int
                ['name'=>'startTime',       'type'=>ORM::STRING,                    ],      // db:text[datetime] FXT
                ['name'=>'endTime',         'type'=>ORM::STRING,                    ],      // db:text[datetime] FXT
                ['name'=>'barModel',        'type'=>ORM::STRING,                    ],      // db:text[enum] references enum_barmodel(type)
                ['name'=>'spread',          'type'=>ORM::FLOAT,                     ],      // db:float
                ['name'=>'bars',            'type'=>ORM::INT,                       ],      // db:int
                ['name'=>'ticks',           'type'=>ORM::INT,                       ],      // db:int
                ['name'=>'tradeDirections', 'type'=>ORM::STRING,                    ],      // db:text[enum] references enum_tradedirection(type)
            ],
            'relations' => [
                ['name'=>'strategyParameters', 'type'=>'one-to-many', 'class'=>StrategyParameter::class, 'ref-column'=>'test_id'],
                ['name'=>'trades',             'type'=>'one-to-many', 'class'=>Order::class,             'ref-column'=>'test_id'],
                ['name'=>'stats',              'type'=>'one-to-one',  'class'=>Statistic::class,         'ref-column'=>'test_id'],
            ],
        ]);
    }


    /**
     * Return the {@link Test} with the given id.
     *
     * @param  int $id - primary key
     *
     * @return ?Test - instance or NULL if no such instance was found
     */
    public function findById($id) {
        Assert::int($id);
        if ($id < 1) throw new InvalidValueException('Invalid argument $id: '.$id);

        $sql = 'select *
                   from :Test
                   where id = '.$id;
        return $this->find($sql);
    }


    /**
     * Find the {@link Test} with the specified reporting symbol.
     *
     * @param  string $symbol - reporting symbol
     *
     * @return ?Test - Test instance or NULL if no such instance exists
     */
    public function findByReportingSymbol($symbol) {
        Assert::string($symbol);

        $symbol = $this->escapeLiteral($symbol);

        $sql = 'select *
                   from :Test
                   where reportingSymbol = '.$symbol;
        return $this->find($sql);
    }
}
