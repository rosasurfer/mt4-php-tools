<?php
namespace rosasurfer\rt\model;

use rosasurfer\db\NoSuchRecordException;
use rosasurfer\db\orm\DAO;
use rosasurfer\exception\IllegalTypeException;

use const rosasurfer\db\orm\meta\INT;
use const rosasurfer\db\orm\meta\STRING;


/**
 * DAO for accessing {@link RosaSymbol} instances.
 */
class RosaSymbolDAO extends DAO {


    /**
     * {@inheritdoc}
     */
    public function getMapping() {
        static $mapping; return $mapping ?: ($mapping=$this->parseMapping([
            'connection' => 'rosatrader',
            'table'      => 't_rosasymbol',
            'class'      => RosaSymbol::class,
            'properties' => [
                ['name'=>'id',                                              'type'=>INT,    'primary'=>true],   // db:int
                ['name'=>'created',                                         'type'=>STRING,                ],   // db:text[datetime] GMT
                ['name'=>'modified',                                        'type'=>STRING, 'version'=>true],   // db:text[datetime] GMT

                ['name'=>'type',                                            'type'=>STRING,                ],   // db:text forex|metals|synthetic
                ['name'=>'group',            'column'=>'groupid',           'type'=>INT,                   ],   // db:int
                ['name'=>'name',                                            'type'=>STRING,                ],   // db:text
                ['name'=>'description',                                     'type'=>STRING,                ],   // db:text
                ['name'=>'digits',                                          'type'=>INT,                   ],   // db:int
                ['name'=>'updateOrder',      'column'=>'updateorder',       'type'=>INT,                   ],   // db:int
                ['name'=>'formula',                                         'type'=>STRING,                ],   // db:text
                ['name'=>'historyStartTick', 'column'=>'historystart_tick', 'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyEndTick',   'column'=>'historyend_tick',   'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyStartM1',   'column'=>'historystart_m1',   'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyEndM1',     'column'=>'historyend_m1',     'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyStartD1',   'column'=>'historystart_d1',   'type'=>STRING,                ],   // db:text[datetime] FXT
                ['name'=>'historyEndD1',     'column'=>'historyend_d1',     'type'=>STRING,                ],   // db:text[datetime] FXT
            ],
            'relations' => [
                ['name'=>'dukascopySymbol', 'assoc'=>'one-to-one', 'type'=>DukascopySymbol::class, 'ref-column'=>'rosasymbol_id'],  // db:int
            ],
        ]));
    }


    /**
     * Find the {@link RosaSymbol} with the specified name.
     *
     * @param  string $name
     *
     * @return RosaSymbol|null
     */
    public function findByName($name) {
        if (!is_string($name)) throw new IllegalTypeException('Illegal type of parameter $name: '.gettype($name));

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
        if (!is_string($name)) throw new IllegalTypeException('Illegal type of parameter $name: '.gettype($name));

        $name = $this->escapeLiteral($name);
        $sql = 'select *
                   from :RosaSymbol
                   where name = '.$name;
        return $this->get($sql);
    }


    /**
     * Find all {@link RosaSymbol} instances in update order.
     *
     * @return RosaSymbol[] - symbol instances sorted for update
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
     * Find all {@link RosaSymbol}s with a Dukascopy mapping in update order.
     *
     * @return RosaSymbol[] - symbol instances sorted for update
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
        if (!is_string($type)) throw new IllegalTypeException('Illegal type of parameter $type: '.gettype($type));

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
