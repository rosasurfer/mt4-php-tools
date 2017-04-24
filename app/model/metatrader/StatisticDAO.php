<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;


/**
 * DAO for accessing {@link Statistic} instances.
 */
class StatisticDAO extends DAO {


    /**
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'class'      => Statistic::class,
            'table'      => 't_statistic',
            'connection' => 'sqlite',
            'properties' => [
                ['name'=>'id'          , 'type'=>PHP_TYPE_INT  , 'primary'=>true         ],      // db:int
                ['name'=>'trades'      , 'type'=>PHP_TYPE_INT  ,                         ],      // db:int
                ['name'=>'tradesPerDay', 'type'=>PHP_TYPE_FLOAT, 'column'=>'trades_day'  ],      // db:float
                ['name'=>'minDuration' , 'type'=>PHP_TYPE_INT  , 'column'=>'duration_min'],      // db:int
                ['name'=>'avgDuration' , 'type'=>PHP_TYPE_INT  , 'column'=>'duration_avg'],      // db:int
                ['name'=>'maxDuration' , 'type'=>PHP_TYPE_INT  , 'column'=>'duration_max'],      // db:int
                ['name'=>'minPips'     , 'type'=>PHP_TYPE_FLOAT, 'column'=>'pips_min'    ],      // db:float
                ['name'=>'avgPips'     , 'type'=>PHP_TYPE_FLOAT, 'column'=>'pips_avg'    ],      // db:float
                ['name'=>'maxPips'     , 'type'=>PHP_TYPE_FLOAT, 'column'=>'pips_max'    ],      // db:float
                ['name'=>'pips'        , 'type'=>PHP_TYPE_FLOAT,                         ],      // db:float
                ['name'=>'profit'      , 'type'=>PHP_TYPE_FLOAT,                         ],      // db:float
                ['name'=>'commission'  , 'type'=>PHP_TYPE_FLOAT,                         ],      // db:float
                ['name'=>'swap'        , 'type'=>PHP_TYPE_FLOAT,                         ],      // db:float
                ['name'=>'test_id'     , 'type'=>PHP_TYPE_INT  ,                         ],      // db:int
            ],
            'relations' => [
                ['name'=>'test', 'relation'=>'one-to-one', 'type'=>Test::class, 'column'=>'test_id'],
            ],
        ]));
    }


    /**
     * Find and return the {@link Statistic} instance of the specified {@link Test}.
     *
     * @param  Test $test
     *
     * @return Statistic
     */
    public function findByTest(Test $test) {
        if (!$test->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($test));

        $test_id = $test->getId();

        $sql = 'select *
                   from :Statistic
                   where test_id = '.$test_id;
        return $this->find($sql);
    }
}
