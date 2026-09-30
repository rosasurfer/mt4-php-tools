<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;

/**
 * TopstepConvertHistoryCommand
 *
 * Convert a Topstep account's trade history from "Dashboard" JSON format to TopstepX's CSV format.
 */
class TopstepConvertHistoryCommand extends Command
{
    /** @var string */
    const DOCOPT = <<<DOCOPT
    Show and/or update locally stored Dukascopy history start times.
    
    Usage:
      {:cmd:}  [-r | -u] [-h] [SYMBOL ...]
    
    Arguments:
      SYMBOL         One or more Dukascopy symbols to process (default: all tracked symbols).
    
    Options:
       -r, --remote  Show remote instead of local history start times (connects to Dukascopy).
       -u, --update  Update history start times (connects to Dukascopy).
       -h, --help    This help screen.
    
    DOCOPT;


    /**
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int {
        return 0;
    }
}
