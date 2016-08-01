<?php
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;


/**
 * DAO zum Zugriff auf ClosedPosition-Instanzen.
 */
class ClosedPositionDAO extends CommonDAO {


   // Datenbankmapping
   public $mapping = array('link'   => 'myfx',
                           'table'  => 't_closedposition',
                           'fields' => array('id'          => array('id'         , self ::T_INT   , self ::T_NOT_NULL),      // int
                                             'version'     => array('version'    , self ::T_STRING, self ::T_NOT_NULL),      // datetime
                                             'created'     => array('created'    , self ::T_STRING, self ::T_NOT_NULL),      // datetime

                                             'ticket'      => array('ticket'     , self ::T_INT   , self ::T_NOT_NULL),      // int
                                             'type'        => array('type'       , self ::T_STRING, self ::T_NOT_NULL),      // string
                                             'lots'        => array('lots'       , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'symbol'      => array('symbol'     , self ::T_STRING, self ::T_NOT_NULL),      // string
                                             'openTime'    => array('opentime'   , self ::T_STRING, self ::T_NOT_NULL),      // datetime
                                             'openPrice'   => array('openprice'  , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'closeTime'   => array('closetime'  , self ::T_STRING, self ::T_NOT_NULL),      // datetime
                                             'closePrice'  => array('closeprice' , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'stopLoss'    => array('stoploss'   , self ::T_FLOAT , self ::T_NULL    ),      // decimal
                                             'takeProfit'  => array('takeprofit' , self ::T_FLOAT , self ::T_NULL    ),      // decimal
                                             'commission'  => array('commission' , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'swap'        => array('swap'       , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'profit'      => array('profit'     , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'magicNumber' => array('magicnumber', self ::T_INT   , self ::T_NULL    ),      // int
                                             'comment'     => array('comment'    , self ::T_STRING, self ::T_NULL    ),      // string
                                             'signal_id'   => array('signal_id'  , self ::T_INT   , self ::T_NOT_NULL),      // int
                                            ));


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
      $results = $this->getListByQuery($sql);

      if ($assocTicket) {
         foreach ($results as $i => $position) {
            $results[(string) $position->getTicket()] = $position;
            unset($results[$i]);
         }
      }
      return $results;
   }
}
