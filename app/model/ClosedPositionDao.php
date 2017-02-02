<?php
use rosasurfer\db\orm\BaseDao;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * DAO zum Zugriff auf ClosedPosition-Instanzen.
 */
class ClosedPositionDao extends BaseDao {


   // Datenbankmapping
   protected $mapping = [
      'connection' => 'myfx',
      'table'      => 't_closedposition',
      'fields'     => [
         'id'          => ['id'         , self::T_INT   , self::T_NOT_NULL],      // int
         'version'     => ['version'    , self::T_STRING, self::T_NOT_NULL],      // datetime
         'created'     => ['created'    , self::T_STRING, self::T_NOT_NULL],      // datetime

         'ticket'      => ['ticket'     , self::T_INT   , self::T_NOT_NULL],      // int
         'type'        => ['type'       , self::T_STRING, self::T_NOT_NULL],      // string
         'lots'        => ['lots'       , self::T_FLOAT , self::T_NOT_NULL],      // decimal
         'symbol'      => ['symbol'     , self::T_STRING, self::T_NOT_NULL],      // string
         'openTime'    => ['opentime'   , self::T_STRING, self::T_NOT_NULL],      // datetime
         'openPrice'   => ['openprice'  , self::T_FLOAT , self::T_NOT_NULL],      // decimal
         'closeTime'   => ['closetime'  , self::T_STRING, self::T_NOT_NULL],      // datetime
         'closePrice'  => ['closeprice' , self::T_FLOAT , self::T_NOT_NULL],      // decimal
         'stopLoss'    => ['stoploss'   , self::T_FLOAT , self::T_NULL    ],      // decimal
         'takeProfit'  => ['takeprofit' , self::T_FLOAT , self::T_NULL    ],      // decimal
         'commission'  => ['commission' , self::T_FLOAT , self::T_NULL    ],      // decimal
         'swap'        => ['swap'       , self::T_FLOAT , self::T_NULL    ],      // decimal
         'grossProfit' => ['profit'     , self::T_FLOAT , self::T_NULL    ],      // decimal
         'netProfit'   => ['netprofit'  , self::T_FLOAT , self::T_NOT_NULL],      // decimal
         'magicNumber' => ['magicnumber', self::T_INT   , self::T_NULL    ],      // int
         'comment'     => ['comment'    , self::T_STRING, self::T_NULL    ],      // string
         'signal_id'   => ['signal_id'  , self::T_INT   , self::T_NOT_NULL],      // int
   ]];


   /**
    * Ob das angegebene Ticket zum angegebenen Signal existiert.
    *
    * @param  Signal $signal - Signal
    * @param  int    $ticket - zu prüfendes Ticket
    *
    * @return bool
    */
   public function isTicket($signal, $ticket) {
      if (!$signal->isPersistent()) throw new InvalidArgumentException('Cannot process non-persistent '.get_class($signal));
      if (!is_int($ticket))         throw new IllegalTypeException('Illegal type of parameter $ticket: '.getType($ticket));

      $signal_id = $signal->getId();

      $sql = "select 1
                 from t_closedposition c
                 where c.signal_id = $signal_id
                   and c.ticket    = $ticket";
      $result = $this->executeSql($sql);
      return (bool) $result['rows'];
   }


   /**
    * Gibt die geschlossenen Positionen des angegebenen Signals zurück.
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
    * Gibt die geschlossenen Positionen des angegebenen Signals zurück.
    *
    * @param  string $alias       - Signalalias
    * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
    *
    * @return ClosedPosition[] - Array von ClosedPosition-Instanzen, aufsteigend sortiert nach {CloseTime,OpenTime,Ticket}
    */
   public function listBySignalAlias($alias, $assocTicket=false) {
      if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));

      $alias = addSlashes($alias);

      $sql = "select c.*
                 from t_signal         s
                 join t_closedposition c on s.id = c.signal_id
                 where s.alias = '$alias'
                 order by c.closetime, c.opentime, c.ticket";
      $results = $this->fetchAll($sql);

      if ($assocTicket) {
         foreach ($results as $i => $position) {
            $results[(string) $position->getTicket()] = $position;
            unset($results[$i]);
         }
      }
      return $results;
   }
}
