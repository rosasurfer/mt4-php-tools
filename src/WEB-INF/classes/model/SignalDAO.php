<?
/**
 * DAO zum Zugriff auf Signal-Instanzen.
 */
class SignalDAO extends CommonDAO {


   // Datenbankmapping
   public $mapping = array('link'   => 'myfx',
                           'table'  => 't_signal',
                           'fields' => array('id'       => array('id'      , self ::T_INT   , self ::T_NOT_NULL),     // int
                                             'version'  => array('version' , self ::T_STRING, self ::T_NOT_NULL),     // datetime
                                             'created'  => array('created' , self ::T_STRING, self ::T_NOT_NULL),     // datetime

                                             'name'     => array('name'    , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'alias'    => array('alias'   , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'refID'    => array('refid'   , self ::T_STRING, self ::T_NOT_NULL),     // string
                                             'currency' => array('currency', self ::T_STRING, self ::T_NOT_NULL),     // string
                                            ));
}
?>
