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

Command line application to work with the Rosatrader history.

Usage:
  rt-history  status      [SYMBOL ...] [-h]
  rt-history  synchronize [SYMBOL ...] [-h]
  rt-history  update      [SYMBOL ...] [-p PERIOD] [-h]

Commands:
  status       Show history status information.
  synchronize  Synchronize start/end times stored in the database with files in the file system.
  update       Update the locally stored history.

Arguments:
  SYMBOL       One or more symbols to process (default: all symbols).

Options:
  -p, --period=ID  The timeframe period to update: TICK | M1 [default: M1].
  -h, --help       This help screen.

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
        echoPre($this->input->getDocoptResult());
        exit();

        $symbols = $this->resolveSymbols();
        if (!$symbols) return $this->error;

        $cmd = null;
        if ($this->input->hasCommand('status'))      $cmd = 'status';
        if ($this->input->hasCommand('synchronize')) $cmd = 'synchronize';

        $this->output->out('[Info]    '.($cmd=='status' ? 'Local history status' : 'Synchronizing history...'));
        $this->output->out($separator='---------------------------------------------------------------------------------------');

        foreach ($symbols as $symbol) {
            if ($cmd == 'status'     ) $symbol->showHistoryStatus();
            if ($cmd == 'synchronize') $symbol->synchronizeHistory();
            Process::dispatchSignals();                                 // process Ctrl-C
        }
        return $this->error = 0;
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
                $this->output->error('Unknown Rosatrader symbol "'.$name.'"');
                $this->error = 1;
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }

        if (!$symbols) {
            if (!$symbols = RosaSymbol::dao()->findAll('select * from :RosaSymbol order by name')) {
                $this->output->out('No Rosatrader symbols found.');
                $this->error = 0;
            }
        }
        return $symbols;
    }
}
