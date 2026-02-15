<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('log_errors',         '1');
ini_set('log_errors_max_len', '0');
ini_set('error_log', __DIR__.'/../../phpstan.log');
