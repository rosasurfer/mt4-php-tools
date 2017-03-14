<?php
/**
 * Project directory layout.
 *
 * In the main configuration these settings are accessible under "app.dir".
 * Specified paths may be absolute or relative to the "root" directory entry.
 */
return [
    'root'   => dirName(dirName(__DIR__)),
    'config' => __DIR__,
    'log'    => 'etc/log',
    'tmp'    => 'etc/tmp',
    'cache'  => 'etc/cache',
    'web'    => 'web',
];
