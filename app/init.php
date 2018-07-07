<?php
use rosasurfer\Application;
use rosasurfer\util\PHP;

// class loader
$appRoot = dirName(__DIR__);
require($appRoot.'/vendor/autoload.php');


// php.ini settings
error_reporting(E_ALL & ~E_DEPRECATED);
PHP::ini_set('log_errors',        1                                );
PHP::ini_set('error_log',         $appRoot.'/etc/log/php-error.log');
PHP::ini_set('session.save_path', $appRoot.'/etc/tmp'              );
PHP::ini_set('default_charset',  'UTF-8'                           );
PHP::ini_set('memory_limit',     '256M'                            );


// create a new application
return new Application([
    'app.dir.root'       => $appRoot,
    'app.dir.config'     => __DIR__.'/config',
    'app.global-helpers' => true,
]);
