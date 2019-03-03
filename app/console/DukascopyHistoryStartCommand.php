<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;

use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\model\DukascopySymbol;


/**
 * DukascopyHistoryStartCommand
 *
 * Show or update Dukascopy history start times.
 */
class DukascopyHistoryStartCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'

Show or update Dukascopy history start times.

Usage:
  rt-dukascopy-status  [-r | -u] [-h] [SYMBOL ...]

Arguments:
  SYMBOL        The Dukascopy symbols to process (default: all tracked symbols).

Options:
   -r --remote  Show remote instead of locally stored history start times (connects to Dukascopy).
   -u --update  Update locally stored history start times (connects to Dukascopy).
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
     * @return int - execution status: 0 for "success"
     */
    protected function execute() {
        $symbols = $this->resolveSymbols();
        if (!$symbols) return $this->error;

        $remote = $this->input->getOption('--remote');
        $update = $this->input->getOption('--update');
        $remoteTimes = [];

        if ($remote || $update) {
            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $remoteTimes = $dukascopy->fetchHistoryStarts();
        }

        if ($update) {
            $this->output->out('[Info]    Updating history start times...');
            $this->output->out($separator='-----------------------------------------------------------------');
            $i = 0;
            foreach ($symbols as $symbol) {
                if ($symbol->updateHistoryStart($remoteTimes[$symbol->getName()])) {
                    $symbol->save();
                    $this->output->out($separator);
                    $i++;
                }
            }
            !$i && $this->output->out('[Info]    All locally tracked symbols up-to-date');
            return $this->error = 0;
        }

        $this->output->out('[Info]    Displaying '.($remote ? 'remote':'local').' Dukascopy history status');
        $separator = '---------------------------------------------------------------------------------';
        foreach ($symbols as $symbol) {
            $this->output->out($separator);
            $symbol->showHistoryStatus(!$remote);
        }
        $this->output->out($separator);
        return $this->error = 0;
    }


    /**
     * Resolve the symbols to process.
     *
     * @return DukascopySymbol[]
     */
    protected function resolveSymbols() {
        $args = $this->input->getArguments('SYMBOL');
        $symbols = [];

        foreach ($args as $name) {
            /** @var DukascopySymbol $symbol */
            $symbol = DukascopySymbol::dao()->findByName($name);
            if (!$symbol) {
                $this->output->error('Unknown or untracked Dukascopy symbol "'.$name.'"');
                $this->error = 1;
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }

        if (!$symbols) {
            if (!$symbols = DukascopySymbol::dao()->findAll('select * from :DukascopySymbol order by name')) {
                $this->output->out('No tracked Dukascopy symbols found.');
                $this->error = 0;
            }
        }
        return $symbols;
    }
}
