<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\console\io\Input;
use rosasurfer\console\io\Output;
use rosasurfer\rt\lib\metatrader\HistoryHeader;
use rosasurfer\rt\lib\metatrader\MetaTraderException;
use rosasurfer\rt\lib\metatrader\MT4;


/**
 * ScaleHistoryCommand
 *
 * A {@link Command} to scale bar data of MetaTrader4 history files.
 */
class ScaleHistoryCommand extends Command {


    /** @var string */
    const DOCOPT = <<<DOCOPT
Scale bar data of a MetaTrader4 history file.

Usage:
  {:cmd:}  FILE (+|-|*|/) <value> [--from=<datetime>] [--to=<datetime>] [--help]

Arguments:
  FILE               History file to process.
  operator           Arithmetic operation to use (* must be quoted).
  <value>            Numeric operand to use.

Options:
  --from=<datetime>  Processing start time (default: start of file).
  --to=<datetime>    Processing end time (default: end of file).
  -h, --help         This help screen.

Examples:
  {:cmd:}  EURUSD1.hst + 0.5012                                                     # add 0.5012 to all bars
  {:cmd:}  EURUSD1.hst '*' 1.1                                                      # scale up all bars by 10%
  {:cmd:}  EURUSD1.hst / 0.9 --from='2022.01.03 10:00' --to='2022.01.06 17:55'      # scale down a bar range by 10%

DOCOPT;

    /** @var string - history file to process */
    protected $file;

    /** @var string */
    protected $operation;

    /** @var double */
    protected $operand;

    /** @var int */
    protected $from = 0;

    /** @var int */
    protected $to = PHP_INT_MAX;


    /**
     * Validate the command line arguments logically (the call syntax is already validated).
     *
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - error status (0 for no error)
     */
    protected function validate(Input $input, Output $output) {
        // FILE
        if (!is_file($file = $input->getArgument('FILE'))) return 1|echoPre('error: file "'.$file.'" not found'.NL.NL.$input->getDocoptResult()->getUsage());
        $this->file = $file;

        // operator
        if      ($input->getArgument('+')) $this->operation = '+';
        else if ($input->getArgument('-')) $this->operation = '-';
        else if ($input->getArgument('*')) $this->operation = '*';
        else if ($input->getArgument('/')) $this->operation = '/';
        else                               return 1|echoPre('error: invalid scaling operator'.NL.NL.$input->getDocoptResult()->getUsage());

        // <value> i.e. operand
        if (!is_numeric($value = $input->getArgument('<value>'))) return 1|echoPre('error: non-numeric <value>'.NL.NL.$input->getDocoptResult()->getUsage());
        $value = (float) $value;
        if (!$value)                                              return 1|echoPre('error: invalid <value> (zero)'.NL.NL.$input->getDocoptResult()->getUsage());
        $this->operand = (float) $value;

        // --from <datetime>
        /** @var string|bool $from */
        $from = $input->getOption('--from');
        if (is_string($from)) {
            if (!is_datetime($from, ['Y.m.d', 'Y.m.d H:i', 'Y.m.d H:i:s'])) return 1|echoPre('error: invalid --from time'.NL.NL.$input->getDocoptResult()->getUsage());
            $this->from = strtotime($from.' GMT');
        }

        // --to <datetime>
        /** @var string|bool $to */
        $to = $input->getOption('--to');
        if (is_string($to)) {
            if (!is_datetime($to, ['Y.m.d', 'Y.m.d H:i', 'Y.m.d H:i:s'])) return 1|echoPre('error: invalid --to time'.NL.NL.$input->getDocoptResult()->getUsage());
            $this->to = strtotime($to.' GMT');
        }
        if (is_string($from) && is_string($to)) {
            if ($this->from > $this->to) return 1|echoPre('error: invalid --from/--to time range'.NL.NL.$input->getDocoptResult()->getUsage());
        }
        return 0;
   }


    /**
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output) {
        $file      = $this->file;
        $operation = $this->operation;
        $operand   = $this->operand;
        $from      = $this->from;
        $to        = $this->to;

        // open history file and read header
        $fileSize = filesize($file);
        if ($fileSize < HistoryHeader::SIZE) return 1|echoPre('error: invalid or unknown file format (file size < min. size of '.HistoryHeader::SIZE.')');
        $hFile = fopen($file, 'r+b');
        try {
            $header = new HistoryHeader(fread($hFile, HistoryHeader::SIZE));
        }
        catch (MetaTraderException $ex) {
            if (strStartsWith($ex->getMessage(), 'version.unsupported'))
                return 1|echoPre('error: unsupported history format in "'.$file.'": '.NL.$ex->getMessage());
            throw $ex;
        }
        $version   = $header->getFormat();
        $digits    = $header->getDigits();
        $barSize   = $version==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        $barFormat = MT4::BAR_getUnpackFormat($version);
        $iBars     = ($fileSize-HistoryHeader::SIZE) / $barSize;
        if (!is_int($iBars)) return 1|echoPre('error: invalid size of file "'.$file.'" (EOF is not on a bar boundary)');
        if ($version != 400) return 1|echoPre('error: processing of history files in format '.$version.' not yet implemented');

        // transformation helper
        $transform = function($value) use ($operation, $operand) {
            switch ($operation) {
                case '+': return $value + $operand;
                case '-': return $value - $operand;
                case '*': return $value * $operand;
                case '/': return $value / $operand;
            }
        };

        // iterate over all bars and transform data
        for ($n=$i=0; $i < $iBars; $i++) {
            $bar = unpack($barFormat, fread($hFile, $barSize));     // read bar

            if ($from <= $bar['time'] && $bar['time'] < $to) {      // transform data
                $bar['open' ] = $transform($bar['open' ]);
                $bar['high' ] = $transform($bar['high' ]);
                $bar['low'  ] = $transform($bar['low'  ]);
                $bar['close'] = $transform($bar['close']);
                $n++;
                fseek($hFile, -$barSize, SEEK_CUR);                 // write bar
                MT4::writeHistoryBar400($hFile, $digits, $bar['time'], $bar['open'], $bar['high'], $bar['low'], $bar['close'], $bar['ticks']);
            }
        }
        fclose($hFile);

        echoPre('success: transformed '.$n.' bars');
        return 0;
    }
}
