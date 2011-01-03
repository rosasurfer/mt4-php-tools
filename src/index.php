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

echoPre('GMT    = '.date(DATE_RFC1123, strToTime('2007-01-30 16:01:15 GMT')));
echoPre('London = '.date(DATE_RFC1123, strToTime('2007-01-30 16:01:15 Europe/London')));
echoPre('Berlin = '.date(DATE_RFC1123, strToTime('2007-01-30 16:01:15 Europe/Berlin')));
echoPre('Sofia  = '.date(DATE_RFC1123, strToTime('2007-01-30 16:01:15 Europe/Sofia')));
*/

FrontController ::processRequest();
?>
