<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;
use rosasurfer\ministruts\process\Process;

use rosasurfer\rt\model\RosaSymbol;

use function rosasurfer\rt\strToPeriod;

/**
 * RosatraderHistoryCommand
 *
 * A {@link Command} to work with the Rosatrader history.
 */
class RosatraderHistoryCommand extends Command
{
    /** @var string */
    const DOCOPT = <<<DOCOPT
    Command line application to work with the Rosatrader history.
    
    Usage:
      {:cmd:}  status      [SYMBOL ...] [-h]
      {:cmd:}  synchronize [SYMBOL ...] [-h]
      {:cmd:}  update      [SYMBOL ...] [-p PERIOD] [-h]
    
    Commands:
      status           Show history status information.
      synchronize      Synchronize history status in the database with history stored in the file system.
      update           Update locally stored history (connects to remote services).
    
    Arguments:
      SYMBOL           One or more symbols to process (default: all symbols).
    
    Options:
      -p, --period=ID  Timeframe period to update: TICK | M1 [default: M1].
      -h, --help       This help screen.
    
    DOCOPT;


    /**
     * {@inheritdoc}
     *
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int {
        $symbols = $this->resolveSymbols();
        if (!$symbols) return $this->status;

        if ($input->hasCommand('status')) {
            return $this->showStatus($symbols);
        }

        if ($input->hasCommand('synchronize')) {
            return $this->synchronizeStatus($symbols);
        }

        if ($input->hasCommand('update')) {
            return $this->updateHistory($symbols);
        }

        $output->error(trim(self::DOCOPT));
        return 1;
    }


    /**
     * Resolve the symbols to process.
     *
     * @return RosaSymbol[]
     */
    protected function resolveSymbols() {
        $input  = $this->input;
        $output = $this->output;

        $args = $input->getArguments('SYMBOL');
        $symbols = [];

        foreach ($args as $name) {
            $symbol = RosaSymbol::dao()->findByName($name);
            if (!$symbol) {
                $output->error('Unknown Rosatrader symbol "'.$name.'"');
                $this->status = 1;
                return [];
            }
            $symbols[$symbol->getName()] = $symbol;                 // using the real name as index removes duplicates
        }

        if (!$symbols) {
            if ($input->hasCommand('update')) {
                $symbols = RosaSymbol::dao()->findAllForUpdate();
            }
            else {
                $symbols = RosaSymbol::dao()->findAll('select * from :RosaSymbol order by name');
            }
            !$symbols && $output->out('No Rosatrader symbols found.');
        }
        return $symbols;
    }


    /**
     * Show history status information.
     *
     * @param  RosaSymbol[] $symbols
     *
     * @return int - execution status (0 for success)
     */
    protected function showStatus(array $symbols) {
        $output = $this->output;
        $output->out('[Info]    Local history status');
        $output->out('---------------------------------------------------------------------------------------');

        foreach ($symbols as $symbol) {
            $symbol->showHistoryStatus();
            Process::dispatchSignals();
        }
        return 0;
    }


    /**
     * Synchronize history status in the database with history stored in the file system.
     *
     * @param  RosaSymbol[] $symbols
     *
     * @return int - execution status (0 for success)
     */
    protected function synchronizeStatus(array $symbols) {
        $output = $this->output;
        $output->out('[Info]    Synchronizing history...');
        $output->out('---------------------------------------------------------------------------------------');

        foreach ($symbols as $symbol) {
            $symbol->synchronizeHistory();
            Process::dispatchSignals();
        }
        return 0;
    }


    /**
     * Update the stored history.
     *
     * @param  RosaSymbol[] $symbols
     *
     * @return int - execution status (0 for success)
     */
    protected function updateHistory(array $symbols) {
        $input  = $this->input;
        $output = $this->output;
        $output->out('[Info]    Updating history...');

        /** @var string $value */
        $value = $input->getOption('--period');
        $period = strToPeriod($value);

        foreach ($symbols as $symbol) {
            $symbol->updateHistory($period);
            Process::dispatchSignals();
        }
        return 0;
    }
}
