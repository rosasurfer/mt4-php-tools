<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;

use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\model\DukascopySymbol;


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
  SYMBOL        The Dukascopy symbols to process (default: all tracked symbols).

Options:
   -r --remote  Show remote instead of local history status (connects to Dukascopy).
   -u --update  Update local history status (connects to Dukascopy).
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
    protected function execute() {
        $symbols = $this->resolveSymbols();
        if (!$symbols)
            return $this->errorStatus;

        $remote = $this->input->getOption('--remote');
        $update = $this->input->getOption('--update');
        $remoteTimes = [];

        if ($remote || $update) {
            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $remoteTimes = $dukascopy->fetchHistoryStarts();
        }

        if ($update) {
            $this->out('[Info]    Updating history start times...');
            $this->out($separator='-----------------------------------------------------------------');
            $i = 0;
            foreach ($symbols as $symbol) {
                if ($symbol->updateHistoryStart($remoteTimes[$symbol->getName()])) {
                    $symbol->save();
                    $this->out($separator);
                    $i++;
                }
            }
            !$i && $this->out('[Info]    All locally tracked symbols up-to-date');
            return $this->errorStatus = 0;
        }

        $this->out('[Info]    Displaying '.($remote ? 'remote':'local').' Dukascopy history status');
        $separator = '---------------------------------------------------------------------------------';
        foreach ($symbols as $symbol) {
            $this->out($separator);
            $symbol->showHistoryStatus(!$remote);
        }
        $this->out($separator);
        return $this->errorStatus = 0;
    }


    /**
     * Resolve the symbols to process.
     *
     * @return DukascopySymbol[]
     */
    protected function resolveSymbols() {
        /** @var array $args */
        $args = $this->input->getArgument('SYMBOL');
        $symbols = [];

        foreach ($args as $name) {
            /** @var DukascopySymbol $symbol */
            $symbol = DukascopySymbol::dao()->findByName($name);
            if (!$symbol) {
                $this->error('Unknown or untracked Dukascopy symbol "'.$name.'"');
                $this->errorStatus = 1;
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }

        if (!$symbols) {
            if (!$symbols = DukascopySymbol::dao()->findAll('select * from :DukascopySymbol order by name')) {
                $this->out('No tracked Dukascopy symbols found.');
                $this->errorStatus = 0;
            }
        }
        return $symbols;
    }
}
