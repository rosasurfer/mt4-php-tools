<?php
namespace rosasurfer\xtrade\model;

use rosasurfer\db\orm\DAO;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use const rosasurfer\PHP_TYPE_FLOAT;
use const rosasurfer\PHP_TYPE_INT;
use const rosasurfer\PHP_TYPE_STRING;


/**
 * DAO zum Zugriff auf ClosedPosition-Instanzen.
 */
class ClosedPositionDAO extends DAO {


    // Datenbankmapping
    protected $mapping = [
        'connection' => 'mysql',
        'table'      => 't_closedposition',
        'columns'    => [
            'id'          => ['column'=>'id'         , 'type'=>PHP_TYPE_INT   , 'primary'=>true],      // db:int
            'created'     => ['column'=>'created'    , 'type'=>PHP_TYPE_STRING,                ],      // db:datetime
            'version'     => ['column'=>'version'    , 'type'=>PHP_TYPE_STRING, 'version'=>true],      // db:datetime

            'ticket'      => ['column'=>'ticket'     , 'type'=>PHP_TYPE_INT   ,                ],      // db:int
            'type'        => ['column'=>'type'       , 'type'=>PHP_TYPE_STRING,                ],      // db:string
            'lots'        => ['column'=>'lots'       , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'symbol'      => ['column'=>'symbol'     , 'type'=>PHP_TYPE_STRING,                ],      // db:string
            'openTime'    => ['column'=>'opentime'   , 'type'=>PHP_TYPE_STRING,                ],      // db:datetime
            'openPrice'   => ['column'=>'openprice'  , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'closeTime'   => ['column'=>'closetime'  , 'type'=>PHP_TYPE_STRING,                ],      // db:datetime
            'closePrice'  => ['column'=>'closeprice' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'stopLoss'    => ['column'=>'stoploss'   , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'takeProfit'  => ['column'=>'takeprofit' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'commission'  => ['column'=>'commission' , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'swap'        => ['column'=>'swap'       , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'grossProfit' => ['column'=>'profit'     , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'netProfit'   => ['column'=>'netprofit'  , 'type'=>PHP_TYPE_FLOAT ,                ],      // db:decimal
            'magicNumber' => ['column'=>'magicnumber', 'type'=>PHP_TYPE_INT   ,                ],      // db:int
            'comment'     => ['column'=>'comment'    , 'type'=>PHP_TYPE_STRING,                ],      // db:string
            'signal_id'   => ['column'=>'signal_id'  , 'type'=>PHP_TYPE_INT   ,                ],      // db:int
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
