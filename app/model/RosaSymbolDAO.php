<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\core\assert\Assert;
use rosasurfer\ministruts\db\NoSuchRecordException;
use rosasurfer\ministruts\db\orm\DAO;
use rosasurfer\ministruts\db\orm\ORM;


/**
 * DAO for accessing {@link RosaSymbol} instances.
 *
 * @phpstan-import-type  ORM_ENTITY from \rosasurfer\ministruts\db\orm\ORM
 */
class RosaSymbolDAO extends DAO {

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     * @phpstan-return ORM_ENTITY
     */
    public function getMapping(): array {
        static $mapping;
        return $mapping ??= $this->parseMapping([
            'class'      => RosaSymbol::class,
            'connection' => 'rosatrader',
            'table'      => 't_rosasymbol',
            'properties' => [
                ['name'=>'id',                                              'type'=>ORM::INT,    'primary-key'=>true],              // db:int
                ['name'=>'created',                                         'type'=>ORM::STRING,                    ],              // db:text[datetime] GMT
                ['name'=>'modified',                                        'type'=>ORM::STRING, 'version'=>true    ],              // db:text[datetime] GMT

                ['name'=>'type',                                            'type'=>ORM::STRING,                    ],              // db:text forex|metals|synthetic
                ['name'=>'group',            'column'=>'groupid',           'type'=>ORM::INT,                       ],              // db:int
                ['name'=>'name',                                            'type'=>ORM::STRING,                    ],              // db:text
                ['name'=>'description',                                     'type'=>ORM::STRING,                    ],              // db:text
                ['name'=>'digits',                                          'type'=>ORM::INT,                       ],              // db:int
                ['name'=>'updateOrder',      'column'=>'updateorder',       'type'=>ORM::INT,                       ],              // db:int
                ['name'=>'formula',                                         'type'=>ORM::STRING,                    ],              // db:text
                ['name'=>'historyStartTick', 'column'=>'historystart_tick', 'type'=>ORM::STRING,                    ],              // db:text[datetime] FXT
                ['name'=>'historyEndTick',   'column'=>'historyend_tick',   'type'=>ORM::STRING,                    ],              // db:text[datetime] FXT
                ['name'=>'historyStartM1',   'column'=>'historystart_m1',   'type'=>ORM::STRING,                    ],              // db:text[datetime] FXT
                ['name'=>'historyEndM1',     'column'=>'historyend_m1',     'type'=>ORM::STRING,                    ],              // db:text[datetime] FXT
                ['name'=>'historyStartD1',   'column'=>'historystart_d1',   'type'=>ORM::STRING,                    ],              // db:text[datetime] FXT
                ['name'=>'historyEndD1',     'column'=>'historyend_d1',     'type'=>ORM::STRING,                    ],              // db:text[datetime] FXT
            ],
            'relations' => [
                ['name'=>'dukascopySymbol', 'type'=>'one-to-one', 'class'=>DukascopySymbol::class, 'ref-column'=>'rosasymbol_id'],  // db:int
            ],
        ]);
    }


    /**
     * Find the {@link RosaSymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return RosaSymbol|null
     */
    public function findByName($name) {
        Assert::string($name);

        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :RosaSymbol
                   where name = '.$name;
        return $this->find($sql);
    }


    /**
     * Get the {@link RosaSymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return RosaSymbol
     *
     * @throws NoSuchRecordException if no such instance was found
     */
    public function getByName($name) {
        Assert::string($name);

        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :RosaSymbol
                   where name = '.$name;
        return $this->get($sql);
    }


    /**
     * Find all {@link RosaSymbol}s.
     *
     * @return RosaSymbol[] - symbol instances sorted in update order
     */
    public function findAllForUpdate() {
        $sql = 'select *
                   from :RosaSymbol
                   order by updateorder, name';
        return $this->findAll($sql);
    }


    /**
     * Find all {@link RosaSymbol}s with a Dukascopy mapping.
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllDukascopyMapped() {
        $sql = 'select r.*
                   from :RosaSymbol      r
                   join :DukascopySymbol d on r.id = d.rosasymbol_id
                   order by r.name';
        return $this->findAll($sql);
    }


    /**
     * Find all {@link RosaSymbol}s with a Dukascopy mapping.
     *
     * @return RosaSymbol[] - symbol instances sorted in update order
     */
    public function findAllDukascopyMappedForUpdate() {
        $sql = 'select r.*
                   from :RosaSymbol      r
                   join :DukascopySymbol d on r.id = d.rosasymbol_id
                   order by r.updateorder, r.name';
        return $this->findAll($sql);
    }


    /**
     * Find all {@link RosaSymbol}s of the specified type.
     *
     * @param  string $type
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllByType($type) {
        Assert::string($type);

        $type = $this->escapeLiteral($type);
        $sql = 'select *
                   from :RosaSymbol
                   where type = '.$type.'
                   order by name';
        return $this->findAll($sql);
    }


    /** @var string[][] - temporarily: index components */
    public static $synthetics = [
        'AUDLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CADLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CHFLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'EURLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'GBPLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'LFXJPY' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'NZDLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'USDLFX' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],

        'AUDFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CADFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'CHFFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'EURFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'GBPFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'JPYFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'USDFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'],
        'NOKFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDNOK'],
        'NZDFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'NZDUSD'],
        'SEKFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'SGDFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSGD'],
        'ZARFXI' => ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDZAR'],

        'EURX'   => ['EURUSD', 'GBPUSD', 'USDCHF', 'USDJPY', 'USDSEK'],
        'USDX'   => ['EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY', 'USDSEK'],
    ];
}
