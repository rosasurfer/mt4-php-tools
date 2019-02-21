<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\console\Input;
use rosasurfer\console\Output;


/**
 * DukascopyHistoryStatusCommand
 */
class DukascopyHistoryStatusCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'

Show or update Dukascopy history status (history start times per timeframe).

Usage:
  rt.dukascopy.status [-r | -u] [-h] [SYMBOL ...]

Arguments:
  SYMBOL        Optional Dukascopy symbols to process (default: all symbols).

Options:
   -r --remote  Show remote instead of local history status (connects to Dukascopy).
   -u --update  Update the local history status with remote data (connects to Dukascopy).
   -h --help    This help screen.

DOCOPT;


    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    protected function configure() {
        $this->setDocoptDefinition(self::DOCOPT);
        return $this;
    }


    /**
     * {@inheritdoc}
     *
     * @return int - execution status code: 0 (zero) for "success"
     */
    protected function execute(Input $input, Output $output) {
        date_default_timezone_set('GMT');

        echoPre($input->getDocoptResult());

        return 0;
    }
}
