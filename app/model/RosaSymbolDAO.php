<?php
namespace rosasurfer\rost\model;

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
                ['name'=>'id',                                             'type'=>INT,    'primary'=>true],                        // db:int
                ['name'=>'created',                                        'type'=>STRING,                ],                        // db:text[datetime] GMT
                ['name'=>'modified',                                       'type'=>STRING, 'version'=>true],                        // db:text[datetime] GMT

                ['name'=>'type',                                           'type'=>STRING,                ],                        // db:text forex|metals|synthetic
                ['name'=>'name',                                           'type'=>STRING,                ],                        // db:text
                ['name'=>'description',                                    'type'=>STRING,                ],                        // db:text
                ['name'=>'digits',                                         'type'=>INT,                   ],                        // db:int
                ['name'=>'tickHistoryFrom', 'column'=>'history_tick_from', 'type'=>STRING,                ],                        // db:text[datetime] FXT
                ['name'=>'tickHistoryTo',   'column'=>'history_tick_to',   'type'=>STRING,                ],                        // db:text[datetime] FXT
                ['name'=>'m1HistoryFrom',   'column'=>'history_M1_from',   'type'=>STRING,                ],                        // db:text[datetime] FXT
                ['name'=>'m1HistoryTo',     'column'=>'history_M1_to',     'type'=>STRING,                ],                        // db:text[datetime] FXT
                ['name'=>'d1HistoryFrom',   'column'=>'history_D1_from',   'type'=>STRING,                ],                        // db:text[datetime] FXT
                ['name'=>'d1HistoryTo',     'column'=>'history_D1_to',     'type'=>STRING,                ],                        // db:text[datetime] FXT
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
        if (!is_string($name)) throw new IllegalTypeException('Illegal type of parameter $name: '.getType($name));

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
        if (!is_string($name)) throw new IllegalTypeException('Illegal type of parameter $name: '.getType($name));

        $name = $this->escapeLiteral($name);

        $sql = 'select *
                   from :RosaSymbol
                   where name = '.$name;
        return $this->get($sql);
    }


    /**
     * Find all {@link RosaSymbol}s with a Dukascopy mapping.
     *
     * @return RosaSymbol[] - symbol instances sorted ascending by name
     */
    public function findAllDukascopyMapped() {
        $sql = "select *
                   from :RosaSymbol      r
                   join :DukascopySymbol d on r.id = d.rosasymbol_id
                   order by r.name";
        return $this->findAll($sql);
    }
}
