<?php
use rosasurfer\Application;

// class loader
require(__DIR__.'/../etc/vendor/autoload.php');


// php.ini settings
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('error_log',         __DIR__.'/../etc/log/php-error.log');
ini_set('session.save_path', __DIR__.'/../etc/tmp'              );
ini_set('default_charset',  'UTF-8'                             );


// create a new application
return new Application([
    'app.dir.root'       => dirName(__DIR__),
    'app.dir.config'     => __DIR__.'/config',
    'app.global-helpers' => true,
]);
