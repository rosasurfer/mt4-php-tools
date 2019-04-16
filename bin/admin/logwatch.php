#!/usr/bin/env php
<?php
/**
 * Scans the application's PHP error logfile for entries and sends them by email to the configured logmessage receivers.
 * If no receivers are configured mail is sent to the system user running the script. Processed log entries are removed
 * from the file.
 */
namespace rosasurfer\rt\bin\admin\logwatch;

use rosasurfer\Application;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\net\mail\Mailer;
use rosasurfer\util\PHP;

require(dirname(realpath(__FILE__)).'/../../app/init.php');
!CLI && exit(1|stderr('error: This script must be executed from a command line interface.'));


// --- Configuration --------------------------------------------------------------------------------------------------------


set_time_limit(0);                                          // no time limit for CLI
$quiet = false;                                             // whether or not "quiet" mode is enabled (e.g. for CRON)


// --- Parse and validate command line arguments ----------------------------------------------------------------------------


/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

foreach ($args as $i => $arg) {
    if ($arg == '-h') { help(); exit(0);                           }    // help
    if ($arg == '-q') { $quiet = true; unset($args[$i]); continue; }    // quiet mode

    stderr('invalid argument: '.$arg);
    !$quiet && help();
    exit(1);
}


// --- Start ----------------------------------------------------------------------------------------------------------------


// (1) define the location of the error log
$errorLog = ini_get('error_log');
if (empty($errorLog) || $errorLog=='syslog') {              // errors are logged elsewhere
    if (empty($errorLog)) $quiet || echoPre('errors are logged elsewhere ('.(CLI     ?    'stderr':'sapi'  ).')');
    else                  $quiet || echoPre('errors are logged elsewhere ('.(WINDOWS ? 'event log':'syslog').')');
    exit(0);
}


// (2) check log file for existence and process it
if (!is_file    ($errorLog)) { $quiet || echoPre('error log empty: '       .$errorLog); exit(0); }
if (!is_writable($errorLog)) {            stderr('cannot access log file: '.$errorLog); exit(1); }
$errorLog = realpath($errorLog);

// rename the file; we don't want to lock it cause doing so could block the main app
$tempName = tempnam(dirname($errorLog), basename($errorLog).'.');
if (!rename($errorLog, $tempName)) {
    stderr('cannot rename log file: '  .$errorLog);
    exit(1);
}

// read the log file line by line
PHP::ini_set('auto_detect_line_endings', 1);
$hFile = fopen($tempName, 'rb');
$line  = $entry = '';
$i = 0;
while (($line=fgets($hFile)) !== false) {
    $i++;
    $line = trim($line, "\r\n");                // PHP doesn't correctly handle EOL_NETSCAPE which is error_log() standard on Windows
    if (strStartsWith($line, '[')) {            // lines starting with a bracket "[" are considered the start of an entry
        processEntry($entry);
        $entry = '';
    }
    $entry .= $line.NL;
}
processEntry($entry);                           // process the last entry (if any)

// delete the processed file
fclose($hFile);
unlink($tempName);


// (3) the ugly end
exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Send a single log entry to the defined error log receivers. The first line is sent as the mail subject and the full
 * log entry as the mail body.
 *
 * @param  string $entry - a single log entry
 */
function processEntry($entry) {
    if (!is_string($entry)) throw new IllegalTypeException('Illegal type of parameter $entry: '.gettype($entry));
    $entry = trim($entry);
    if (!strlen($entry)) return;

    $config = Application::getConfig();
    $receivers = [];

    foreach (explode(',', $config->get('log.mail.receiver', '')) as $receiver) {
        if ($receiver = trim($receiver)) {
            if (filter_var($receiver, FILTER_VALIDATE_EMAIL)) {     // silently skip invalid receiver addresses
                $receivers[] = $receiver;
            }
        }
    }                                                               // without receivers mail is sent to the system user
    !$receivers && $receivers[] = strtolower(get_current_user().'@localhost');

    $subject = strtok($entry, "\r\n");                              // that's CR or LF, not CRLF
    $message = $entry;
    $sender  = null;
    $headers = [];

    static $mailer; if (!$mailer) {
        $options = [];
        if (strlen($name = $config->get('log.mail.profile', ''))) {
            $options = $config->get('mail.profile.'.$name, []);
            $sender  = $config->get('mail.profile.'.$name.'.from', null);
            $headers = $config->get('mail.profile.'.$name.'.headers', []);
        }
        $mailer = Mailer::create($options);
    }

    global $quiet;
    $quiet || echoPre(substr($subject, 0, 80).'...');

    foreach ($receivers as $receiver) {
        $mailer->sendMail($sender, $receiver, $subject, $message, $headers);
    }
}


/**
 * Syntax helper.
 *
 * @param  string $message [optional] - additional message to display (default: none)
 */
function help($message = null) {
    if (isset($message))
        echo $message.NL;

    $self = basename($_SERVER['PHP_SELF']);

echo <<<HELP

 Syntax:  $self [options]

 Options:  -q   Quiet mode. Suppress status messages but not errors (for scripted execution, e.g. by CRON).
           -h   This help screen.


HELP;
}
