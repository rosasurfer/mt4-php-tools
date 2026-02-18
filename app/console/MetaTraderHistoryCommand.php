<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\Application;
use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;
use rosasurfer\ministruts\core\exception\IllegalStateException;
use rosasurfer\ministruts\process\Process;

use rosasurfer\rt\lib\metatrader\MetaTrader;
use rosasurfer\rt\model\RosaSymbol;

use const rosasurfer\ministruts\DAY;

/**
 * MetaTraderHistoryCommand
 *
 * A {@link Command} to work with MetaTrader history files.
 */
class MetaTraderHistoryCommand extends Command
{
    /** @var string */
    const DOCOPT = <<<DOCOPT
    Create new MetaTrader history files.
    
    Usage:
      {:cmd:}  create SYMBOL [options]
    
    Commands:
      create       Create new MetaTrader history files (all standard timeframes) for the specified symbol.
    
    Arguments:
      SYMBOL       The symbol to process history for.
    
    Options:
       -h, --help  This help screen.
    
    DOCOPT;


    /**
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int {
        $symbol = $this->resolveSymbol();
        if (!$symbol) return $this->status;

        $start = (int) $symbol->getHistoryStartM1('U');
        $end   = (int) $symbol->getHistoryEndM1('U');           // starttime of the last bar
        if (!$start) {
            $output->out('[Info]    '.str_pad($symbol->getName(), 6).'  no Rosatrader history available');
            return 1;
        }
        if (!$end) throw new IllegalStateException('Rosatrader history start/end time mis-match for '.$symbol->getName().':  start='.$start.'  end='.$end);

        /** @var MetaTrader $metatrader */
        $metatrader = Application::service(MetaTrader::class);
        $historySet = $metatrader->createHistorySet($symbol);

        // iterate over existing history
        for ($day=$start, $lastMonth=0; $day <= $end; $day+=1*DAY) {
            $month = (int) gmdate('m', $day);
            if ($month != $lastMonth) {
                $output->out('[Info]    '.gmdate('M-Y', $day));
                $lastMonth = $month;
            }
            if ($symbol->isTradingDay($day)) {
                if (!$bars = $symbol->getHistoryM1($day)) {
                    return 1;
                }
                $historySet->appendBars($bars);
                Process::dispatchSignals();                     // check for Ctrl-C
            }
        }
        $historySet->close();
        $output->out('[Ok]      '.$symbol->getName());

        return 0;
    }


    /**
     * Resolve the symbol to process.
     *
     * @return RosaSymbol|null
     */
    protected function resolveSymbol() {
        $input  = $this->input;
        $output = $this->output;

        $name = $input->getArgument('SYMBOL');

        if (!$symbol = RosaSymbol::dao()->findByName($name)) {
            $output->error('Unknown Rosatrader symbol "'.$name.'"');
            $this->status = 1;
        }
        return $symbol;
    }
}
