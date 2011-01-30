<?
include(dirName(__FILE__).'/WEB-INF/include/defines.php');
include(dirName(__FILE__).'/WEB-INF/classes/classes.php');

define('APPLICATION_NAME', 'fx.pewasoft');


FrontController ::processRequest();


/*
function mt4_date($timestamp, $format='Y.m.d H:i:s') {
   static $date = null;
   if (!$date)
      $date = new DateTime(null, new DateTimeZone('GMT'));

   $date->modify("2004-03-01");
   //$date->modify("@$timestamp");

   return $date->format($format.' e (O) I');
}

$timestamp = strToTime('2010-10-02 17:00:00 America/New_York');
date_default_timezone_set('Europe/Sofia'    ); echoPre('Sofia    = '.date(DATE_RFC1123.' I', $timestamp));
date_default_timezone_set('Europe/Berlin'   ); echoPre('Berlin   = '.date(DATE_RFC1123.' I', $timestamp));
date_default_timezone_set('Europe/London'   ); echoPre('London   = '.date(DATE_RFC1123.' I', $timestamp));
date_default_timezone_set('GMT'             ); echoPre('GMT      = '.date(DATE_RFC1123.' I', $timestamp));
date_default_timezone_set('America/New_York'); echoPre('New York = '.date(DATE_RFC1123.' I', $timestamp));

echoPre("\n\n".'mt4_date: '.mt4_date($timestamp));
echoPre(       'gmDate:   '.gmDate('Y.m.d H:i:s e (O) I', $timestamp));
*/
?>
