<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\NoSuchRecordException;
use rosasurfer\ministruts\db\orm\DAO;
use rosasurfer\ministruts\db\orm\ORM;


/**
 * DAO for accessing {@link DukascopySymbol} instances.
 *
 * @phpstan-import-type ORM_ENTITY from \rosasurfer\ministruts\phpstan\CustomTypes
 */
class DukascopySymbolDAO extends DAO {

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     * @phpstan-return ORM_ENTITY
     */
    public function getMapping(): array {
        static $mapping;
        return $mapping ??= $this->parseMapping([
            'class'      => DukascopySymbol::class,
            'connection' => 'rosatrader',
            'table'      => 't_dukascopysymbol',
            'properties' => [
                ['name'=>'id',                                              'type'=>ORM::INT,    'primary-key'=>true],      // db:int
                ['name'=>'created',                                         'type'=>ORM::STRING,                    ],      // db:text[datetime] GMT
                ['name'=>'modified',                                        'type'=>ORM::STRING, 'version'=>true    ],      // db:text[datetime] GMT

                ['name'=>'name',                                            'type'=>ORM::STRING,                    ],      // db:text
                ['name'=>'digits',                                          'type'=>ORM::INT,                       ],      // db:int
                ['name'=>'historyStartTick', 'column'=>'historystart_tick', 'type'=>ORM::STRING,                    ],      // db:text[datetime] FXT
                ['name'=>'historyStartM1',   'column'=>'historystart_m1',   'type'=>ORM::STRING,                    ],      // db:text[datetime] FXT
                ['name'=>'historyStartH1',   'column'=>'historystart_h1',   'type'=>ORM::STRING,                    ],      // db:text[datetime] FXT
                ['name'=>'historyStartD1',   'column'=>'historystart_d1',   'type'=>ORM::STRING,                    ],      // db:text[datetime] FXT
            ],
            'relations' => [
                ['name'=>'rosaSymbol', 'type'=>'one-to-one', 'class'=>RosaSymbol::class, 'column'=>'rosasymbol_id'],        // db:int
            ],
        ]);
    }


    /**
     * Find the {@link DukascopySymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return ?DukascopySymbol
     */
    public function findByName(string $name): ?DukascopySymbol {
        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :DukascopySymbol
                   where name = '.$name;
        return $this->find($sql);
    }


    /**
     * Get the {@link DukascopySymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return DukascopySymbol
     *
     * @throws NoSuchRecordException if no such instance was found
     */
    public function getByName(string $name): DukascopySymbol {
        $name = $this->escapeLiteral($name);
        $sql = "select *
                   from :DukascopySymbol
                   where name = $name";
        return $this->get($sql);
    }
}
