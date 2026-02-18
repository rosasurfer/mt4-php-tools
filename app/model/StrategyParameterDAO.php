<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\DAO;
use rosasurfer\ministruts\db\orm\ORM;

/**
 * DAO for accessing {@link StrategyParameter} instances.
 *
 * @phpstan-import-type ORM_ENTITY from \rosasurfer\ministruts\phpstan\UserTypes
 */
class StrategyParameterDAO extends DAO
{
    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     * @phpstan-return ORM_ENTITY
     */
    public function getMapping(): array {
        static $mapping;
        return $mapping ??= $this->parseMapping([
            'class'      => StrategyParameter::class,
            'connection' => 'rosatrader',
            'table'      => 't_strategyparameter',
            'properties' => [
                ['name'=>'id',    'type'=>ORM::INT,    'primary-key'=>true],                        // db:int
                ['name'=>'name',  'type'=>ORM::STRING,                    ],                        // db:text
                ['name'=>'value', 'type'=>ORM::STRING,                    ],                        // db:text
            ],
            'relations' => [
                ['name'=>'test', 'type'=>'many-to-one', 'class'=>Test::class, 'column'=>'test_id'], // db:int
            ],
        ]);
    }
}
