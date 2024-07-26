<?php
namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\DAO;

use const rosasurfer\ministruts\db\orm\meta\INT;
use const rosasurfer\ministruts\db\orm\meta\STRING;


/**
 * DAO for accessing {@link StrategyParameter} instances.
 */
class StrategyParameterDAO extends DAO {


    /**
     *
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_strategyparameter',
            'class'      => StrategyParameter::class,
            'properties' => [
                ['name'=>'id',    'type'=>INT,    'primary'=>true],                                 // db:int
                ['name'=>'name',  'type'=>STRING,                ],                                 // db:text
                ['name'=>'value', 'type'=>STRING,                ],                                 // db:text
            ],
            'relations' => [
                ['name'=>'test', 'assoc'=>'many-to-one', 'type'=>Test::class, 'column'=>'test_id'], // db:int
            ],
        ]));
    }
}
