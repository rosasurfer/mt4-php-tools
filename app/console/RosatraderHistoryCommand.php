<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\process\Process;

use rosasurfer\rt\model\RosaSymbol;


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
        $symbols = $this->resolveSymbols();
        if (!$symbols)
            return $this->errorStatus;

        $cmd = null;
        if ($this->input->getCommand('status'))      $cmd = 'status';
        if ($this->input->getCommand('synchronize')) $cmd = 'synchronize';

        $this->out('[Info]    '.($cmd=='status' ? 'Local history status' : 'Synchronizing history...'));
        $this->out($separator='---------------------------------------------------------------------------------------');

        foreach ($symbols as $symbol) {
            if ($cmd == 'status'     ) $symbol->showHistoryStatus();
            if ($cmd == 'synchronize') $symbol->synchronizeHistory();
            Process::dispatchSignals();                                 // process Ctrl-C
        }
        return $this->errorStatus = 0;
    }


    /**
     * Resolve the symbols to process.
     *
     * @return RosaSymbol[]
     */
    protected function resolveSymbols() {
        $args = $this->input->getArguments('SYMBOL');
        $symbols = [];

        foreach ($args as $name) {
            /** @var RosaSymbol $symbol */
            $symbol = RosaSymbol::dao()->findByName($name);
            if (!$symbol) {
                $this->error('Unknown Rosatrader symbol "'.$name.'"');
                $this->errorStatus = 1;
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }

        if (!$symbols) {
            if (!$symbols = RosaSymbol::dao()->findAll('select * from :RosaSymbol order by name')) {
                $this->out('No Rosatrader symbols found.');
                $this->errorStatus = 0;
            }
        }
        return $symbols;
    }
}
