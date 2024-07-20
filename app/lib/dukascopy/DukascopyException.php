<?php
namespace rosasurfer\rt\lib\dukascopy;

use rosasurfer\core\exception\RosasurferException;
use rosasurfer\core\exception\RosasurferExceptionTrait;


/**
 * DukascopyException
 *
 * Exception marking Dukascopy related errors.
 */
class DukascopyException extends RosasurferException {

    use RosasurferExceptionTrait;               // repeated use (workaround for PDT 7.2 bug)
}
