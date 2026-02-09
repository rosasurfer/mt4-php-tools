<?php
declare(strict_types=1);

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\util\PHP;

// class loader
require(__DIR__.'/../vendor/autoload.php');

// php.ini settings
PHP::ini_set('log_errors',              '1'                          );
PHP::ini_set('log_errors_max_len',      '0'                          );
PHP::ini_set('error_log',               APP_ROOT.'/log/php-error.log');
PHP::ini_set('session.save_path',       APP_ROOT.'/etc/tmp'          );
PHP::ini_set('session.use_strict_mode', '1'                          );
PHP::ini_set('default_charset',         'UTF-8'                      );
PHP::ini_set('memory_limit',            '128M'                       );
set_include_path(APP_ROOT.'/vendor');

// create a new application
return new Application([
    'app.dir.root'            => APP_ROOT,
    'app.dir.config'          => APP_ROOT.'/config',
    'app.error-handler.mode'  => 'exception',
    'app.error-handler.level' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,
]);
