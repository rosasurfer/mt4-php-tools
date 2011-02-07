<?php
/**
 * MT4HistoryFile
 *
 * Repräsentiert ein HistoryFile im MT4-Format mit erweiterten Eigenschaften.
 */
class MT4HistoryFile extends Object {

   protected /*string*/ $fileName;

   protected /*int*/    $version;
   protected /*string*/ $description;
   protected /*string*/ $symbol;
   protected /*int*/    $period;
   protected /*int*/    $digits;
   protected /*int*/    $timesign;
   protected /*int*/    $lastsync;
   protected /*int*/    $barCount;
   protected /*int*/    $startTime;
   protected /*int*/    $endTime;
   protected /*string*/ $timezone;
}
?>
