<?php
namespace rosasurfer\trade\model\metatrader;

use rosasurfer\db\orm\DAO;

use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;
use const rosasurfer\db\orm\ID_PRIMARY;


/**
 * DAO for accessing {@link StrategyParameter} instances.
 */
class StrategyParameterDAO extends DAO {


    /**
     * @var array - database mapping
     */
    protected $mapping = [
        'connection' => 'sqlite',
        'table'      => 't_strategyparameter',
        'columns'    => [
            'id'      => ['id'     , PHP_TYPE_INT   , 0, ID_PRIMARY],    // db:int
            'name'    => ['name'   , PHP_TYPE_STRING, 0, 0         ],    // db:text
            'value'   => ['value'  , PHP_TYPE_STRING, 0, 0         ],    // db:text
            'test_id' => ['test_id', PHP_TYPE_INT   , 0, 0         ],    // db:int
    ]];
}
