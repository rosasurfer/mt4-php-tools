<?
include(dirName(__FILE__).'/WEB-INF/include/defines.php');
include(dirName(__FILE__).'/WEB-INF/classes/classes.php');

define('APPLICATION_NAME', 'fx.pewasoft');

/*
date_default_timezone_set('America/New_York');
$date = strToTime('2010-11-02 17:00:00');

date_default_timezone_set('Europe/Sofia');
echoPre('Sofia(DATE_RFC1123) = '.date(DATE_RFC1123, $date));

date_default_timezone_set('Europe/London');
echoPre('London(DATE_RFC1123) = '.date(DATE_RFC1123, $date));

date_default_timezone_set('GMT');
echoPre('GMT(DATE_RFC1123) = '.date(DATE_RFC1123, $date));

date_default_timezone_set('America/New_York');
echoPre('New York(DATE_RFC1123) = '.date(DATE_RFC1123, $date));
*/

FrontController ::processRequest();
?>
