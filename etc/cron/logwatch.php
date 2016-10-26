#!/usr/bin/php
<?php
/**
 * Scans the application's PHP error log file for entries and notifies by email of any findings. Mails will be sent to
 * the configured log message receivers of the application. If there are no configured receivers mail will be sent to
 * the system user running this script. After notification the found entries are removed from the log file.
 *
 * If this script is run by CRON there is no way that errors might be missed.  If the script itself causes errors the
 * error messages are printed to STDERR and catched by CRON which again will notify the system user running this script.
 *
 * TODO: Error messages must not be printed to STDOUT but to STDERR.
 * TODO: Parameter for suppresing/not suppressing regular output to enable status messages when not run by CRON.
 */
use rosasurfer\config\Config;
use rosasurfer\exception\IllegalTypeException;

use function rosasurfer\echoPre;
use function rosasurfer\strStartsWith;

use const rosasurfer\WINDOWS;


require(__DIR__.'/../../app/init.php');
set_time_limit(0);                                       // no time limit for CLI


// (1) define error log sender and receivers
// read the regularily configured receivers
$config = Config::getDefault();
$sender = $config->get('mail.from', get_current_user().'@localhost');
$receivers = [];
foreach (explode(',', $config->get('log.mail.receiver', '')) as $receiver) {
   if ($receiver=trim($receiver))
      $receivers[] = $receiver;                          // @TODO: validate address format
}

// check setting "mail.forced-receiver" (may be set in development)
if ($receivers && $forcedReceivers=$config->get('mail.forced-receiver', false)) {
   $receivers = [];
   foreach (explode(',', $forcedReceivers) as $receiver) {
      if ($receiver=trim($receiver))
         $receivers[] = $receiver;
   }
}

// w/o receiver mail is sent to the current system user
!$receivers && $receivers[]=get_current_user().'@localhost';


// (2) define the location of the error log
$errorLog = ini_get('error_log');
if (empty($errorLog) || $errorLog=='syslog') {           // errors are sent to the SAPI logger or system logger
   echoPre('errors are logged elsewhere: '.$errorLog=='syslog' ? $errorLog:'sapi');
   exit(0);
}


// (3) check log file for existence and process it
if (!is_file    ($errorLog)) { echoPre('error log does not exist: '.$errorLog); exit(0); }
if (!is_writable($errorLog)) { echoPre('cannot access log file: '  .$errorLog); exit(1); }
$errorLog = realPath($errorLog);

// rename the file (we don't want to lock it, doing so could block the main app)
$tempName = tempNam(dirName($errorLog), baseName($errorLog));
if (!rename($errorLog, $tempName)) {
   echoPre('cannot rename log file: '  .$errorLog);
   exit(1);
}

// read the log file line by line
ini_set('auto_detect_line_endings', 1);
$hFile = fOpen($tempName, 'rb');
$line  = $entry = '';
$i = 0;
while (($line=fGets($hFile)) !== false) {
   $i++;
   if (strStartsWith($line, '[')) {                      // lines starting with "[" are considered the start of an entry
      processEntry($entry);
      $entry = '';
   }
   $entry .= $line;
}
processEntry($entry);                                    // process the last entry (if any)

// delete the processed file
fClose($hFile);
unlink($tempName);


// (4) The ugly end
exit(0);


// --- function definitions --------------------------------------------------------------------------------------------


/**
 * Send a single log entry to the defined error log receivers. The first line is sent as the mail subject and the full
 * log entry as the mail body.
 *
 * @param  string $entry - a single or multi line log entry
 */
function processEntry($entry) {
   if (!is_string($entry)) throw new IllegalTypeException('Illegal type of parameter $entry: '.getType($entry));
   $entry = trim($entry);
   if (!strLen($entry))    return;

   global $sender, $receivers;

   //echoPre('sending log entry...');

   // normalize line-breaks
   $entry = str_replace(["\r\n", "\r"], "\n", $entry);            // use Unix line-breaks by default but...
   if (WINDOWS)                                                   // use Windows line-breaks on Windows
      $entry = str_replace("\n", "\r\n", $entry);
   $entry = str_replace(chr(0), "?", $entry);                     // replace NUL bytes which destroy the mail

   $subject = strTok($entry, "\r\n");                             // that's CR or LF, not CRLF
   $message = $entry;

   // send log entry to receivers
   foreach ($receivers as $receiver) {
      // Linux:   "From:" header is not reqired but may be set
      // Windows: mail() fails if "sendmail_from" is not set and "From:" header is missing
      mail($receiver, $subject, $message, $headers='From: '.$sender);
   }
}
