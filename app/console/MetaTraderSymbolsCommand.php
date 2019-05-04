<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\console\io\Input;
use rosasurfer\console\io\Output;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\metatrader\MetaTrader;


/**
 * MetaTraderSymbolsCommand
 *
 * A {@link Command} to process MetaTrader "symbols.raw" files.
 */
class MetaTraderSymbolsCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'

Create, modify or display MetaTrader "symbols.raw" files.

Usage:
  rt-metatrader-symbol  split <symbols.raw> [OPTIONS]
  rt-metatrader-symbol  merge <symbol>... [OPTIONS]

Commands:
  split        Split a "symbols.raw" file into separate files per symbol. Files are stored in the current working directory.
  merge        Create a new "symbols.raw" file from one or more symbol definitions.

Arguments:
  symbols.raw  The file to split by symbol.
  symbol       Symbol definition file (with wildcards) to merge into a "symbols.raw" file.

Options:
   -h, --help  This help screen.

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
        return 0;
    }
}
