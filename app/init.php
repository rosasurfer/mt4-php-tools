<?php
use rosasurfer\Application;
use rosasurfer\util\PHP;
error_reporting(E_ALL & ~E_DEPRECATED);

// class loader
require(($appRoot=dirname(__DIR__)).'/vendor/autoload.php');

// php.ini settings
PHP::ini_set('log_errors',        1                                );
PHP::ini_set('error_log',         $appRoot.'/etc/log/php-error.log');
PHP::ini_set('session.save_path', $appRoot.'/etc/tmp'              );
PHP::ini_set('default_charset',  'UTF-8'                           );
PHP::ini_set('memory_limit',     '256M'                            );

// create a new application
return new Application([
    'app.dir.root'   => $appRoot,
    'app.dir.config' => __DIR__.'/config',
    'app.globals'    => true,
]);
