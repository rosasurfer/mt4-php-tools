<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;

use rosasurfer\rt\lib\metatrader\Symbol;

/**
 * MetaTraderSymbolsCommand
 *
 * A {@link Command} to process MetaTrader "symbols.raw" files.
 */
class MetaTraderSymbolsCommand extends Command
{
    /** @var string */
    const DOCOPT = <<<DOCOPT
    Create, modify or display MetaTrader symbol definitions ("symbols.raw" files).
    
    Usage:
      {:cmd:}  split SYMBOLS_RAW [-h]
      {:cmd:}  join  SYMBOL... [-h]
    
    Commands:
      split        Split a "symbols.raw" file into separate files per symbol. Files are stored in the current working directory.
      join         Create a new "symbols.raw" file from one or more separate symbol definition files.
    
    Arguments:
      SYMBOLS_RAW  The file to split by symbol.
      SYMBOL       Symbol definition file(s) to join into one "symbols.raw" file. May contain wildcards.
    
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
        if ($input->hasCommand('split')) {
            return $this->splitSymbols();
        }
        if ($input->hasCommand('join')) {
            return $this->joinSymbols();
        }

        $output->error(trim(self::DOCOPT));
        return 1;
    }


    /**
     * Split a "symbols.raw" file into separate symbol definitions.
     *
     * @return int - execution status (0 for success)
     */
    protected function splitSymbols() {
        $input  = $this->input;
        $output = $this->output;

        $sourceFile = $input->getArgument('SYMBOLS_RAW');
        if (!is_file($sourceFile) || !is_readable($sourceFile)) {
            $output->error('error: Argument "'.$sourceFile.'" either is not a file or is not readable.');
            return 1;
        }

        // validate file size
        $fileSize = filesize($sourceFile);
        if (!$fileSize || $fileSize % Symbol::SIZE) {
            $output->error('error: Invalid file size of "'.$sourceFile.'", not a multiple of symbol size ('.Symbol::SIZE.')');
            return 1;
        }

        // process the file
        $hSource = fopen($sourceFile, 'rb');
        try {
            $offset = 0;
            $format = Symbol::unpackFormat();

            // read symbols and store them one by one
            while ($offset < $fileSize) {
                $symbol = unpack('@0'.$format, $chunk=fread($hSource, Symbol::SIZE));
                $symbolFile = $symbol['name'].'.raw';
                if (is_file($symbolFile)) {
                    $output->error('error: File "'.$symbolFile.'" already exists');
                    return 2;
                }
                $hSymbol = fopen($symbolFile, 'xb');
                fwrite($hSymbol, $chunk);
                fclose($hSymbol);

                $offset += Symbol::SIZE;
            }
            $output->out('Processed '.($fileSize/Symbol::SIZE).' symbols');
        }
        finally {
            fclose($hSource);
        }
        return 0;
    }


    /**
     * Create a new "symbols.raw" file from one or more separate symbol definition files.
     *
     * @return int - execution status (0 for success)
     */
    protected function joinSymbols() {
        $this->output->error('error: command "join" not yet implemented');
        return 1;
    }
}
