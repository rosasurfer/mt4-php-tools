<?php
declare(strict_types=1);

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

/**
 * Prints a variable in a formatted and pretty way.
 * Alias in the global namespace.
 *
 * @param  mixed $var
 *
 * @return void
 */
function echof($var): void {
    \rosasurfer\ministruts\echof($var);
}

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
