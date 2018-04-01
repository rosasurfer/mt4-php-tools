<?php declare(strict_types=1);

include(__DIR__.'/../../vendor/autoload.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/src/global-helper.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/etc/phpstan/DynamicReturnType.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/etc/phpstan/DAO_Find_ReturnType.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/etc/phpstan/DAO_FindAll_ReturnType.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/etc/phpstan/PersistableObject_Dao_ReturnType.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/etc/phpstan/PersistableObject_PopulateNew_ReturnType.php');
include(__DIR__.'/../../vendor/rosasurfer/ministruts/etc/phpstan/Singleton_GetInstance_ReturnType.php');


if (!\rosasurfer\ini_get_bool('short_open_tag')) {
    echo 'Error: The PHP configuration value "short_open_tag" must be enabled (security).'.PHP_EOL;
    exit(1);
}
