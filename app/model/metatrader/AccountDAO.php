<?php
namespace rosasurfer\xtrade\model\metatrader;

use rosasurfer\db\orm\DAO;
use rosasurfer\exception\IllegalTypeException;

use const rosasurfer\PHP_TYPE_BOOL;
use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;

use const rosasurfer\db\orm\BIND_TYPE_INT;
use const rosasurfer\db\orm\F_NOT_NULLABLE;
use const rosasurfer\db\orm\ID_CREATE;
use const rosasurfer\db\orm\ID_PRIMARY;
use const rosasurfer\db\orm\ID_VERSION;


/**
 * DAO zum Zugriff auf Account-Instanzen.
 */
class AccountDAO extends DAO {


    // Datenbankmapping
    protected $mapping = [
        'connection' => 'xtrade',
        'table'      => 't_account',
        'columns'    => [
            'id'                  => ['id'                 , PHP_TYPE_INT   , 0            , ID_PRIMARY               ],     // db:int
            'created'             => ['created'            , PHP_TYPE_STRING, 0            , ID_CREATE                ],     // db:datetime
            'version'             => ['version'            , PHP_TYPE_STRING, 0            , ID_VERSION|F_NOT_NULLABLE],     // db:timestamp

            'company'             => ['company'            , PHP_TYPE_STRING, 0            , 0                        ],     // db:string
            'number'              => ['number'             , PHP_TYPE_STRING, 0            , 0                        ],     // db:string
            'demo'                => ['demo'               , PHP_TYPE_BOOL  , BIND_TYPE_INT, 0                        ],     // db:tinyint
            'type'                => ['type'               , PHP_TYPE_STRING, 0            , 0                        ],     // db:enum
            'timezone'            => ['timezone'           , PHP_TYPE_STRING, 0            , 0                        ],     // db:string
            'currency'            => ['currency'           , PHP_TYPE_STRING, 0            , 0                        ],     // db:string
            'balance'             => ['balance'            , PHP_TYPE_FLOAT , 0            , 0                        ],     // db:decimal
            'lastReportedBalance' => ['lastreportedbalance', PHP_TYPE_FLOAT , 0            , 0                        ],     // db:decimal
            'lastUpdate'          => ['lastupdate'         , PHP_TYPE_STRING, 0            , 0                        ],     // db:datetime
            'mtiAccountId'        => ['mtiaccount_id'      , PHP_TYPE_STRING, 0            , 0                        ],     // db:string
    ]];


    /**
     * Gibt einen einzelnen Account zurueck.
     *
     * @param  string $company - company name
     * @param  string $number  - account number
     *
     * @return Account
     */
    public function getByCompanyAndNumber($company, $number) {
        if (!is_string($company)) throw new IllegalTypeException('Illegal type of parameter $company: '.getType($company));
        if (!is_string($number))  throw new IllegalTypeException('Illegal type of parameter $number: '.getType($number));

        $company = $this->escapeLiteral($company);
        $number  = $this->escapeLiteral($number);

        $sql = "select *
                      from :Account
                      where company = $company
                         and number  = $number";
        return $this->findOne($sql);
    }
}
