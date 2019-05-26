<?php
namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\core\exception\RuntimeException;


/**
 * MetaTraderException
 *
 * Exception marking MetaTrader related errors.
 */
class MetaTraderException extends RuntimeException {

    const ERR_FILESIZE_INSUFFICIENT = 1;
}
