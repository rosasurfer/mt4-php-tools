<?php
/**
 * Project directory layout.
 *
 * These settings are accessible in the main configuration under "app.dir". Relative paths are interpreted
 * relative to the "root" value. The "root" value must be an absolute path.
 */
return [
    'root'  => dirName(dirName(__DIR__)),
    'cache' => 'etc/cache',
    'data'  => 'data',
    'log'   => 'etc/log',
    'tmp'   => 'etc/tmp',
    'web'   => 'web',
];
