<?
/**
 * DAO zum Zugriff auf Signal-Instanzen.
 */
class SignalDAO extends CommonDAO {


   // Datenbankmapping
   public $mapping = array('link'   => 'myfx',
                           'table'  => 't_signal',
                           'fields' => array('id'         => array('id'         , self ::T_INT   , self ::T_NOT_NULL),     // int
                                             'version'    => array('version'    , self ::T_STRING, self ::T_NOT_NULL),     // datetime
                                             'created'    => array('created'    , self ::T_STRING, self ::T_NOT_NULL),     // datetime

                                             'name'       => array('name'       , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'alias'      => array('alias'      , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'referenceID'=> array('referenceid', self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'currency'   => array('currency'   , self ::T_STRING, self ::T_NOT_NULL),     // string
                                            ));


   /**
    * Gibt das Signal mit der angegebenen ID zurück.
    *
    * @param  int $id - Signal-ID (PK)
    *
    * @return Signal instance
    */
   public function getById($id) {
      if (!is_int($id)) throw new IllegalTypeException('Illegal type of parameter $id: '.getType($id));
      if ($id < 1)      throw new plInvalidArgumentException('Invalid argument $id: '.$id);

      $sql = "select *
                 from t_signal
                 where id = $id";
      return $this->getByQuery($sql);
   }


   /**
    * Gibt das Signal mit dem angegebenen Alias zurück.
    *
    * @param  string $alias - Signalalias
    *
    * @return Signal instance
    */
   public function getByAlias($alias) {
      if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));
      if ($alias === '')      throw new plInvalidArgumentException('Invalid argument $alias: '.$alias);

      $alias = addSlashes($alias);

      $sql = "select *
                 from t_signal
                 where alias = '$alias'";
      return $this->getByQuery($sql);
   }


   /**
    * Gibt die ID des Signals mit dem angegebenen Alias zurück.
    *
    * @param  string $alias - Signalalias
    *
    * @return int - Signal-ID (primary key) oder NULL, wenn es kein solches Signal gibt
    */
   public function getIdByAlias($alias) {
      if (!is_string($alias)) throw new IllegalTypeException('Illegal type of parameter $alias: '.getType($alias));
      if ($alias === '')      throw new plInvalidArgumentException('Invalid argument $alias: '.$alias);

      $alias = addSlashes($alias);

      $sql = "select id
                 from t_signal
                 where alias = '$alias'";
      $result = $this->executeSql($sql);

      if ($result['rows'])
         return (int) mysql_result($result['set'], 0);
      return null;
   }
}
?>
