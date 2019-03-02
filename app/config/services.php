<?php
use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\lib\metatrader\MetaTrader;


/**
 * Service configuration
 *
 * @see  https://github.com/rosasurfer/ministruts/blob/master/src/di/README.md
 */
return [
    Dukascopy::class  => Dukascopy::class,
    MetaTrader::class => MetaTrader::class,
];
