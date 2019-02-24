<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;


/**
 * MetaTraderHistoryCommand
 *
 * A {@link Command} to work with MetaTrader history files.
 */
class MetaTraderHistoryCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'

Create, update or show status information about MetaTrader history files.

Usage:
  rt.metatrader.history create SYMBOL [options]

Commands:
  create      Create a new MetaTrader history set (all standard timeframes).

Arguments:
  SYMBOL      RosaTrader symbol to use for history processing.

Options:
   -h --help  This help screen.

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
    protected function execute() {
        //echoPre($this->input->getDocoptResult());
        return $this->errorStatus = 0;
    }
}
