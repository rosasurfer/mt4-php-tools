<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\console\Input;
use rosasurfer\console\Output;
use rosasurfer\process\Process;

use rosasurfer\rt\model\DukascopySymbol;
use rosasurfer\rt\lib\dukascopy\Dukascopy;


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
  SYMBOL        Optional Dukascopy symbols to process (default: all tracked symbols).

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

        // (1) resolve the symbols to process
        /** @var array $args */
        $args = $input->getArgument('SYMBOL');
        $symbols = [];
        foreach ($args as $name) {
            /** @var DukascopySymbol $symbol */
            $symbol = DukascopySymbol::dao()->findByName($name);
            if (!$symbol) {
                $output->stderr('error: unknown or untracked Dukascopy symbol "'.$name.'"');
                return 1;
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }
        if (!$symbols) {
            foreach (DukascopySymbol::dao()->findAll() as $symbol) {
                $symbols[$symbol->getName()] = $symbol;
            }
            if (!$symbols) {
                $output->stdout('No tracked Dukascopy symbols found.');
                return 0;
            }
            ksort($symbols);
        }

        // (2) process the symbols
        $remote = $input->getOption('--remote');
        $update = $input->getOption('--update');
        if ($remote || $update) {
            $output->stdout('[Info]    fetching history start times from Dukascopy...');

            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $starttimes = $dukascopy->fetchHistoryStarts();
        }

        if ($update) {
        }
        else {
            $separator = '---------------------------------------------------------------------------------------';
            foreach ($symbols as $symbol) {
                $output->stdout($separator);
                $symbol->showHistoryStatus(!$remote);
                Process::dispatchSignals();
            }
            $output->stdout($separator);
        }
        return 0;
    }
}
