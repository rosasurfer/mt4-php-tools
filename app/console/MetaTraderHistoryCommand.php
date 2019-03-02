<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\exception\IllegalStateException;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\metatrader\MetaTrader;
use rosasurfer\rt\model\RosaSymbol;


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
  rt-metatrader-history  create SYMBOL [options]

Commands:
  create      Create new MetaTrader history files for the given symbol (all standard timeframes).

Arguments:
  SYMBOL      The symbol to process history for.

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
     * @return int - execution status: 0 for "success"
     */
    protected function execute() {
        $symbol = $this->resolveSymbol();
        if (!$symbol) return $this->errorStatus;

        $start = (int) $symbol->getHistoryStartM1('U');
        $end   = (int) $symbol->getHistoryEndM1('U');           // starttime of the last bar
        if (!$start) {
            $this->output->out('[Info]    '.str_pad($symbol->getName(), 6).'  no Rosatrader history available');
            return $this->errorStatus = 1;
        }
        if (!$end) throw new IllegalStateException('Rosatrader history start/end mis-match for '.$symbol->getName().':  start='.$start.'  end='.$end);

        /** @var MetaTrader $mt */
        $mt = $this->di(MetaTrader::class);
        $historySet = $mt->createHistorySet($symbol);

        // iterate over existing history
        for ($day=$start, $lastMonth=-1; $day <= $end; $day+=1*DAY) {
            $month = (int) gmdate('m', $day);
            if ($month != $lastMonth) {
                $this->output->out('[Info]    '.gmdate('M-Y', $day));
                $lastMonth = $month;
            }
            if ($symbol->isTradingDay($day)) {
                if (!$bars = $symbol->getHistoryM1($day))
                    return $this->errorStatus = 1;
                $historySet->appendBars($bars);
                Process::dispatchSignals();                     // check for Ctrl-C
            }
        }
        $historySet->close();
        $this->output->out('[Ok]      '.$symbol->getName());

        return $this->errorStatus = 0;
    }


    /**
     * Resolve the symbol to process.
     *
     * @return RosaSymbol|null
     */
    protected function resolveSymbol() {
        $name = $this->input->getArgument('SYMBOL');

        if (!$symbol = RosaSymbol::dao()->findByName($name)) {
            $this->error('Unknown Rosatrader symbol "'.$name.'"');
            $this->errorStatus = 1;
        }
        return $symbol;
    }
}
