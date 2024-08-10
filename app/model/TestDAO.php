<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\db\orm\DAO;

use const rosasurfer\ministruts\db\orm\meta\FLOAT;
use const rosasurfer\ministruts\db\orm\meta\INT;
use const rosasurfer\ministruts\db\orm\meta\STRING;


/**
 * DAO for accessing {@link Test} instances.
 */
class TestDAO extends DAO {


    /**
     * {@inheritdoc}
     *
     * @return array<string, string|array<scalar[]>>
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_test',
            'class'      => Test::class,
            'properties' => [
                ['name'=>'id',              'type'=>INT,    'primary'=>true],    // db:int
                ['name'=>'created',         'type'=>STRING,                ],    // db:text[datetime] GMT
                ['name'=>'modified',        'type'=>STRING, 'version'=>true],    // db:text[datetime] GMT

                ['name'=>'strategy',        'type'=>STRING,                ],    // db:text
                ['name'=>'reportingId',     'type'=>INT,                   ],    // db:int
                ['name'=>'reportingSymbol', 'type'=>STRING,                ],    // db:text
                ['name'=>'symbol',          'type'=>STRING,                ],    // db:text
                ['name'=>'timeframe',       'type'=>INT,                   ],    // db:int
                ['name'=>'startTime',       'type'=>STRING,                ],    // db:text[datetime] FXT
                ['name'=>'endTime',         'type'=>STRING,                ],    // db:text[datetime] FXT
                ['name'=>'barModel',        'type'=>STRING,                ],    // db:text[enum] references enum_barmodel(type)
                ['name'=>'spread',          'type'=>FLOAT,                 ],    // db:float
                ['name'=>'bars',            'type'=>INT,                   ],    // db:int
                ['name'=>'ticks',           'type'=>INT,                   ],    // db:int
                ['name'=>'tradeDirections', 'type'=>STRING,                ],    // db:text[enum] references enum_tradedirection(type)
            ],
            'relations' => [
                ['name'=>'strategyParameters', 'assoc'=>'one-to-many', 'type'=>StrategyParameter::class, 'ref-column'=>'test_id'],
                ['name'=>'trades',             'assoc'=>'one-to-many', 'type'=>Order::class,             'ref-column'=>'test_id'],
                ['name'=>'stats',              'assoc'=>'one-to-one',  'type'=>Statistic::class,         'ref-column'=>'test_id'],
            ],
        ]));
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
