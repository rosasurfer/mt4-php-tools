<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;


/**
 * RosatraderHistoryCommand
 *
 * A {@link Command} to work with the Rosatrader history.
 */
class RosatraderHistoryCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'

Command line application to manage the Rosatrader history.

Usage:
  rt-history  (status | synchronize) [SYMBOL ...] [-h]

Commands:
  status       Show history status information.
  synchronize  Synchronize start/end times in the database with the files in the file system.

Arguments:
  SYMBOL       The symbols to process (default: all symbols).

Options:
   -h --help   This help screen.

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
     * @return int - execution status: 0 for "success"
     */
    protected function execute() {
        //echoPre($this->input->getDocoptResult());

        if ($this->input->getCommand('status')) {
        }
        else if ($this->input->getCommand('synchronize')) {
        }
        return $this->errorStatus = 0;
    }
}
