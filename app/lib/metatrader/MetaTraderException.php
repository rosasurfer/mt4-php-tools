<?php
namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\core\exception\RosasurferException;
use rosasurfer\core\exception\RosasurferExceptionTrait;


/**
 * MetaTraderException
 *
 * Exception marking MetaTrader related errors.
 */
class MetaTraderException extends RosasurferException {

    use RosasurferExceptionTrait;               // repeated use (workaround for PDT 7.2 bug)

    const ERR_FILESIZE_INSUFFICIENT = 1;
}
