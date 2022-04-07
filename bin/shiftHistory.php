#!/usr/bin/env php
<?php
/**
 *
 */
use rosasurfer\rt\lib\metatrader\HistoryHeader;
use rosasurfer\rt\lib\metatrader\MetaTraderException;
use rosasurfer\rt\lib\metatrader\MT4;

require(dirname(realpath(__FILE__)).'/../app/init.php');


// --- input validation -----------------------------------------------------------------------------------------------------

// read and validate cmd line arguments
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);
(sizeof($args) != 2) && exit(1|help());

// next argument: history file
$fileName = array_shift($args);
!is_file($fileName) && exit(1|echoPre('error: file not found "'.$fileName.'"'));

// next argument: shift value
$shift = array_shift($args);
!strIsNumeric($shift) && exit(1|echoPre('error: non-numeric shift value "'.$shift.'"'));
$shift = (float) $shift;


// --- open history file ----------------------------------------------------------------------------------------------------

$fileSize = filesize($fileName);
($fileSize < HistoryHeader::SIZE) && exit(1|echoPre('error: invalid or unknown file format (file size < min. size of '.HistoryHeader::SIZE.')'));

$hFile = fopen($fileName, 'r+b');
$header = null;
try {
    $header = new HistoryHeader(fread($hFile, HistoryHeader::SIZE));
}
catch (MetaTraderException $ex) {
    strStartsWith($ex->getMessage(), 'version.unsupported') && exit(1|echoPre('error: unsupported history format in "'.$fileName.'": '.NL.$ex->getMessage()));
    throw $ex;
}

$version   = $header->getFormat();
$digits    = $header->getDigits();
$barSize   = $version==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
$barFormat = MT4::BAR_getUnpackFormat($version);
$iBars     = ($fileSize-HistoryHeader::SIZE) / $barSize;
!is_int($iBars)   && exit(1|echoPre('error: unexpected EOF of "'.$fileName.'"'));
($version != 400) && exit(1|echoPre('error: processing of history files in format '.$version.' not yet implemented'));


// --- iterate over and shift all bars --------------------------------------------------------------------------------------

for ($i=0; $i < $iBars; $i++) {
    // read next bar
    $bar = unpack($barFormat, fread($hFile, $barSize));

    // shift bar
    $bar['open' ] += $shift;
    $bar['high' ] += $shift;
    $bar['low'  ] += $shift;
    $bar['close'] += $shift;

    // write bar
    fseek($hFile, -$barSize, SEEK_CUR);
    MT4::writeHistoryBar400($hFile, $digits, $bar['time'], $bar['open'], $bar['high'], $bar['low'], $bar['close'], $bar['ticks']);
}
fclose($hFile);

echoPre('success: shifted '.$i.' bars');
exit(0);


// --- functions ------------------------------------------------------------------------------------------------------------


/**
 * Show usage syntax.
 */
function help() {
    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP

  Syntax:  $self  FILE  SHIFT

  FILE:   history file to process
  SHIFT:  numeric shift value

HELP;
}
