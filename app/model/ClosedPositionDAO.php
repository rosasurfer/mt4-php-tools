<?php
namespace rosasurfer\xtrade\model;

use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;

use const rosasurfer\db\orm\F_NOT_NULLABLE;
use const rosasurfer\db\orm\ID_CREATE;
use const rosasurfer\db\orm\ID_PRIMARY;
use const rosasurfer\db\orm\ID_VERSION;


/**
 * DAO zum Zugriff auf ClosedPosition-Instanzen.
 */
class ClosedPositionDAO extends DAO {


    // Datenbankmapping
    protected $mapping = [
        'connection' => 'mysql',
        'table'      => 't_closedposition',
        'columns'    => [
            'id'          => ['id'         , PHP_TYPE_INT   , 0, ID_PRIMARY               ],      // db:int
            'created'     => ['created'    , PHP_TYPE_STRING, 0, ID_CREATE                ],      // db:datetime
            'version'     => ['version'    , PHP_TYPE_STRING, 0, ID_VERSION|F_NOT_NULLABLE],      // db:datetime

            'ticket'      => ['ticket'     , PHP_TYPE_INT   , 0, 0                        ],      // db:int
            'type'        => ['type'       , PHP_TYPE_STRING, 0, 0                        ],      // db:string
            'lots'        => ['lots'       , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'symbol'      => ['symbol'     , PHP_TYPE_STRING, 0, 0                        ],      // db:string
            'openTime'    => ['opentime'   , PHP_TYPE_STRING, 0, 0                        ],      // db:datetime
            'openPrice'   => ['openprice'  , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'closeTime'   => ['closetime'  , PHP_TYPE_STRING, 0, 0                        ],      // db:datetime
            'closePrice'  => ['closeprice' , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'stopLoss'    => ['stoploss'   , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'takeProfit'  => ['takeprofit' , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'commission'  => ['commission' , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'swap'        => ['swap'       , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'grossProfit' => ['profit'     , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'netProfit'   => ['netprofit'  , PHP_TYPE_FLOAT , 0, 0                        ],      // db:decimal
            'magicNumber' => ['magicnumber', PHP_TYPE_INT   , 0, 0                        ],      // db:int
            'comment'     => ['comment'    , PHP_TYPE_STRING, 0, 0                        ],      // db:string
            'signal_id'   => ['signal_id'  , PHP_TYPE_INT   , 0, 0                        ],      // db:int
    ]];


    /**
     * Ob das angegebene Ticket zum angegebenen Signal existiert.
     *
     * @param  Signal $signal - Signal
     * @param  int    $ticket - zu pruefendes Ticket
     *
     * @return bool
     */
    public function isTicket($signal, $ticket) {
        if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
        if (!is_int($ticket))         throw new IllegalTypeException('Illegal type of parameter $ticket: '.getType($ticket));

        $signal_id = $signal->getId();

        $sql = "select count(*)
                      from :ClosedPosition c
                      where c.signal_id = $signal_id
                         and c.ticket    = $ticket";
        return $this->query($sql)->fetchBool();
    }


    /**
     * Gibt die geschlossenen Positionen des angegebenen Signals zurueck.
     *
     * @param  Signal $signal      - Signal
     * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
     *
     * @return ClosedPosition[] - Array von ClosedPosition-Instanzen, aufsteigend sortiert nach {CloseTime,OpenTime,Ticket}
     */
    public function listBySignal(Signal $signal, $assocTicket=false) {
        if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
        return $this->listBySignalAlias($signal->getAlias(), $assocTicket);
    }


    /**
     * Gibt die geschlossenen Positionen des angegebenen Signals zurueck.
     *
     * @param  string $alias       - Signalalias
     * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
     *
     * @return ClosedPosition[] - Array von ClosedPosition-Instanzen, aufsteigend sortiert nach {CloseTime,OpenTime,Ticket}
     */
    public function listBySignalAlias($alias, $assocTicket=false) {
        if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));

        $alias = $this->escapeLiteral($alias);

        $sql = "select c.*
                   from :Signal         s
                   join :ClosedPosition c on s.id = c.signal_id
                   where s.alias = $alias
                   order by c.closetime, c.opentime, c.ticket";
        /** @var ClosedPosition[] $results */
        $results = $this->findAll($sql);

        if ($assocTicket) {
            foreach ($results as $i => $position) {
                $results[(string) $position->getTicket()] = $position;
                unset($results[$i]);
            }
        }
        return $results;
    }
}
