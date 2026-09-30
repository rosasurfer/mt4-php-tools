<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;

use function rosasurfer\ministruts\strEndsWithI;
use function rosasurfer\ministruts\strLeftTo;

use const rosasurfer\ministruts\NL;


/**
 * TopstepConvertHistoryCommand
 *
 * Convert a Topstep account's trade history from "Dashboard" JSON format to TopstepX's CSV format.
 */
class TopstepConvertHistoryCommand extends Command
{
    /** @var string */
    const DOCOPT = <<<DOCOPT
    Convert a Topstep account history from "Dashboard" JSON format to TopstepX's CSV format.
    
    Usage:
      {:cmd:}  -i INPUT_FILE [-o OUTPUT_FILE] [-h]
    
    Options:
      -i INPUT_FILE   JSON input file to convert.
      -o OUTPUT_FILE  CSV output file name (default: input file name with .csv extension).
      -h, --help      This help screen.
    
    DOCOPT;

    /** @var string */
    protected $inputFile;

    /** @var string */
    protected $outputFile;


    /**
     * Validate command line arguments logically.
     *
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - error status (0 for no error)
     */
    protected function validate(Input $input, Output $output): int {
        $error = function($status, $message) use ($input, $output) {
            $usage = $input->getDocoptResult()->getUsage();
            $output->error($message.NL.NL.$usage);
            return $status;
        };

        // -i
        if (!is_file($inputFile = $input->getOption('-i'))) return $error(1, 'error: input file "'.$inputFile.'" not found');
        $this->inputFile = $inputFile;

        // -o
        $outputFile = $input->getOption('-o');
        if (is_string($outputFile)) {
            if (!strEndsWithI($outputFile, '.csv')) {
                $outputFile .= '.csv';
            }
        }
        elseif (strEndsWithI($inputFile, '.json')) {
            $outputFile = strLeftTo($inputFile, '.', -1).'.csv';
        }
        else {
            $outputFile = $inputFile.'.csv';
        }
        if (is_file($outputFile)) return $error(1, 'error: output file "'.$outputFile.'" already exists');
        $this->outputFile = $outputFile;

        return 0;
    }


    /**
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int {

        echof('input:  '.$this->inputFile);
        echof('output: '.$this->outputFile);

        return 0;
    }
}
