<?php
declare(strict_types=1);

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\util\PHP;

// class loader
require(__DIR__.'/../vendor/autoload.php');

if (!function_exists('ddd')) {
    /**
     * Helper for dump-driven development. Appends a stringified variable to a dump file. If the file doesn't exist it is created.
     * Alias in the global namespace.
     *
     * @param  mixed  $var
     * @param  bool   $reset    [optional] - whether to reset the dump file (default: no)
     * @param  string $filename [optional] - custom dump filename, remembered (default: dirname(ini_get('error_log')).'/ddd.log')
     *
     * @return mixed - any value to make the call an expression
     */
    function ddd($var, bool $reset = false, string $filename = '') {
        return \rosasurfer\ministruts\ddd($var, $reset, $filename);
    }
}

if (!function_exists('echof')) {
    /**
     * Prints a variable in a formatted and pretty way.
     * Alias in the global namespace.
     *
     * @param  mixed $var
     *
     * @return void
     */
    function echof($var): void {
        \rosasurfer\ministruts\ddd($var);
    }
}

if (!function_exists('toString')) {
    /**
     * Convert any variable to a nicely formatted string. Can also be used to format JSON strings.
     * Alias in the global namespace.
     *
     * @param  mixed $var
     *
     * @return string
     */
    function toString($var): string {
        return \rosasurfer\ministruts\toString($var);
    }
}

$rootDir = dirname(__DIR__);

// php.ini settings
PHP::ini_set('log_errors',              '1'                         );
PHP::ini_set('log_errors_max_len',      '0'                         );
PHP::ini_set('error_log',               "$rootDir/log/php-error.log");
PHP::ini_set('session.save_path',       "$rootDir/etc/tmp"          );
PHP::ini_set('session.use_strict_mode', '1'                         );
PHP::ini_set('default_charset',         'UTF-8'                     );
PHP::ini_set('memory_limit',            '128M'                      );
set_include_path("$rootDir/vendor");

// create a new application
return new Application([
    'app.dir.root'            => $rootDir,
    'app.dir.config'          => $rootDir.'/config',
    'app.error-handler.mode'  => 'exception',
    'app.error-handler.level' => E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED,
]);
