<?php
/**
 * DAO zum Zugriff auf OpenPosition-Instanzen.
 */
class OpenPositionDAO extends CommonDAO {


   // Datenbankmapping
   public $mapping = array('link'   => 'myfx',
                           'table'  => 't_openposition',
                           'fields' => array('id'          => array('id'         , self ::T_INT   , self ::T_NOT_NULL),      // int
                                             'version'     => array('version'    , self ::T_STRING, self ::T_NOT_NULL),      // datetime
                                             'created'     => array('created'    , self ::T_STRING, self ::T_NOT_NULL),      // datetime

                                             'ticket'      => array('ticket'     , self ::T_INT   , self ::T_NOT_NULL),      // int
                                             'type'        => array('type'       , self ::T_STRING, self ::T_NOT_NULL),      // string
                                             'lots'        => array('lots'       , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'symbol'      => array('symbol'     , self ::T_STRING, self ::T_NOT_NULL),      // string
                                             'openTime'    => array('opentime'   , self ::T_STRING, self ::T_NOT_NULL),      // datetime
                                             'openPrice'   => array('openprice'  , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'stopLoss'    => array('stoploss'   , self ::T_FLOAT , self ::T_NULL    ),      // decimal
                                             'takeProfit'  => array('takeprofit' , self ::T_FLOAT , self ::T_NULL    ),      // decimal
                                             'commission'  => array('commission' , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'swap'        => array('swap'       , self ::T_FLOAT , self ::T_NOT_NULL),      // decimal
                                             'magicNumber' => array('magicnumber', self ::T_INT   , self ::T_NULL    ),      // int
                                             'comment'     => array('comment'    , self ::T_STRING, self ::T_NULL    ),      // string
                                             'signal_id'   => array('signal_id'  , self ::T_INT   , self ::T_NOT_NULL),      // int
                                            ));


   /**
    * Gibt die offenen Positionen des angegebenen Signals zurück.
    *
    * @param  Signal $signal      - Signal
    * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
    *
    * @return OpenPosition[] - Array von OpenPosition-Instanzen, aufsteigend sortiert nach {OpenTime,Ticket}
    */
   public function listBySignal(Signal $signal, $assocTicket=false) {
      return $this->listBySignalAlias($signal->getAlias(), $assocTicket);
   }


   /**
    * Gibt die offenen Positionen des angegebenen Signals zurück.
    *
    * @param  string $alias       - Signalalias
    * @param  bool   $assocTicket - ob das Ergebnisarray assoziativ nach Tickets organisiert werden soll (default: nein)
    *
    * @return OpenPosition[] - Array von OpenPosition-Instanzen, aufsteigend sortiert nach {OpenTime,Ticket}
    */
   public function listBySignalAlias($alias, $assocTicket=false) {
      if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));

      $alias = addSlashes($alias);

      $sql = "select o.*
                 from t_signal       s
                 join t_openposition o on s.id = o.signal_id
                 where s.alias = '$alias'
                 order by o.opentime, o.ticket";
      $results = $this->getListByQuery($sql);

      if ($assocTicket) {
         foreach ($results as $i => $position) {
            $results[(string) $position->getTicket()] = $position;
            unset($results[$i]);
         }
      }
      return $results;
   }


   /**
    * Gibt zu einem angegebenen Ticket die offene Position zurück.
    *
    * @param  string $signalAlias - Signalalias
    * @param  int    $ticket      - Ticket
    *
    * @return OpenPosition instance
    */
   public function getByTicket($signalAlias, $ticket) {
      if (!is_string($signalAlias)) throw new IllegalTypeException('Illegal type of parameter $signalAlias: '.getType($signalAlias));
      if (!is_int($ticket))         throw new IllegalTypeException('Illegal type of parameter $ticket: '.getType($ticket));

      $alias = addSlashes($signalAlias);

      $sql = "select o.*
                 from t_signal       s
                 join t_openposition o on s.id = o.signal_id
                 where s.alias = '$alias'
                    and o.ticket = $ticket";
      return $this->getByQuery($sql);
   }
}
?>
