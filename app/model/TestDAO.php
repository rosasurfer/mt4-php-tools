<?php
namespace rosasurfer\rost\model;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\db\orm\meta\FLOAT;
use const rosasurfer\db\orm\meta\INT;
use const rosasurfer\db\orm\meta\STRING;


/**
 * DAO for accessing {@link Test} instances.
 */
class TestDAO extends DAO {


    /**
     * {@inheritdoc}
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
     * @return Test|null - instance or NULL if no such instance was found
     */
    public function findById($id) {
        if (!is_int($id)) throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
        if ($id < 1)      throw new InvalidArgumentException('Invalid argument $id: '.$id);

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
     * @return Test|null - Test instance or NULL if no such instance exists
     */
    public function findByReportingSymbol($symbol) {
        if (!is_string($symbol)) throw new IllegalTypeException('Illegal type of parameter $symbol: '.getType($symbol));

        $symbol = $this->escapeLiteral($symbol);

        $sql = 'select *
                   from :Test
                   where reportingSymbol = '.$symbol;
        return $this->find($sql);
    }
}
