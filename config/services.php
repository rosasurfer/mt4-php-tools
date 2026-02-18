<?php
declare(strict_types=1);

use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\lib\metatrader\MetaTrader;

/**
 * Service configuration
 *
 * @see  https://github.com/rosasurfer/ministruts/blob/master/src/core/di/
 */
return [
    Dukascopy::class  => Dukascopy::class,
    MetaTrader::class => MetaTrader::class,
];
