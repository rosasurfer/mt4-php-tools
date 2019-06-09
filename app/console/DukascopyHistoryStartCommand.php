<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\console\io\Input;
use rosasurfer\console\io\Output;

use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\model\DukascopySymbol;


/**
 * DukascopyHistoryStartCommand
 *
 * Show and update locally stored Dukascopy history start times.
 */
class DukascopyHistoryStartCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'

Show and update locally stored Dukascopy history start times.

Usage:
  rt-dukascopy-status  [-r | -u] [-h] [SYMBOL ...]

Arguments:
  SYMBOL         Dukascopy symbols to process (default: all tracked symbols).

Options:
   -r, --remote  Show remote instead of locally stored history start times (connects to Dukascopy).
   -u, --update  Update locally stored history start times (connects to Dukascopy).
   -h, --help    This help screen.

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
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output) {
        $symbols = $this->resolveSymbols();
        if (!$symbols) return $this->status;

        $remote = $input->getOption('--remote');
        $update = $input->getOption('--update');
        $remoteTimes = [];

        if ($remote || $update) {
            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $remoteTimes = $dukascopy->fetchHistoryStarts();
        }

        if ($update) {
            $output->out('[Info]    Updating history start times...');
            $output->out($separator='-----------------------------------------------------------------');
            $i = 0;
            foreach ($symbols as $symbol) {
                if ($symbol->updateHistoryStart($remoteTimes[$symbol->getName()])) {
                    $symbol->save();
                    $output->out($separator);
                    $i++;
                }
            }
            !$i && $output->out('[Info]    All locally tracked symbols up-to-date');
            return 0;
        }

        $output->out('[Info]    Displaying '.($remote ? 'remote':'local').' Dukascopy history status');
        $separator = '---------------------------------------------------------------------------------';
        foreach ($symbols as $symbol) {
            $output->out($separator);
            $symbol->showHistoryStatus(!$remote);
        }
        $output->out($separator);
        return 0;
    }


    /**
     * Resolve the symbols to process.
     *
     * @return DukascopySymbol[]
     */
    protected function resolveSymbols() {
        /** @var Input $input */
        $input = $this->di(Input::class);
        /** @var Output $output */
        $output = $this->di(Output::class);

        $args = $input->getArguments('SYMBOL');
        $symbols = [];

        foreach ($args as $name) {
            /** @var DukascopySymbol $symbol */
            $symbol = DukascopySymbol::dao()->findByName($name);
            if (!$symbol) {
                $output->error('Unknown or untracked Dukascopy symbol "'.$name.'"');
                $this->status = 1;
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }

        if (!$symbols && !$symbols = DukascopySymbol::dao()->findAll('select * from :DukascopySymbol order by name'))
            $output->out('No tracked Dukascopy symbols found.');
        return $symbols;
    }
}
