<?php
declare(strict_types=1);

namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\ministruts\core\exception\Exception as RosasurferException;

/**
 * MetaTraderException
 *
 * Exception marking MetaTrader related errors.
 */
class MetaTraderException extends RosasurferException
{
    const ERR_FILESIZE_INSUFFICIENT = 1;
}
