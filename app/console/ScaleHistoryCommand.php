<?php
declare(strict_types=1);

namespace rosasurfer\rt\console;

use rosasurfer\ministruts\console\Command;
use rosasurfer\ministruts\console\io\Input;
use rosasurfer\ministruts\console\io\Output;
use rosasurfer\ministruts\core\exception\InvalidValueException;
use rosasurfer\ministruts\util\DateTime;

use rosasurfer\rt\lib\metatrader\HistoryHeader;
use rosasurfer\rt\lib\metatrader\MetaTraderException;
use rosasurfer\rt\lib\metatrader\MT4;

use function rosasurfer\ministruts\pluralize;
use function rosasurfer\ministruts\strStartsWith;

use const rosasurfer\ministruts\DAY;
use const rosasurfer\ministruts\NL;


/**
 * ScaleHistoryCommand
 *
 * A {@link Command} to scale the bars of a MetaTrader4 history file.
 */
class ScaleHistoryCommand extends Command {


    /** @var string */
    const DOCOPT = <<<DOCOPT
Scale bar data of a MetaTrader4 history file.

Usage:
  {:cmd:}  FILE (+|-|*|/) <value> [--from=<datetime>] [--to=<datetime>] [--fix-bars] [--help]

Arguments:
  FILE               History file to process.
  operator           Arithmetic operation to use (* must be quoted).
  <value>            Numeric operand to use.

Options:
  --from=<datetime>  Processing start time (default: start of file).
  --to=<datetime>    Processing end time (default: end of file).
  --fix-bars         Fix invalid ranges of modified bars (e.g. high/low mis-matches)
  --help, -h         This help screen.

Examples:
  {:cmd:}  EURUSD1.hst + 0.5012                                             # add 0.5012 to all bars
  {:cmd:}  EURUSD1.hst '*' 1.1                                              # scale up all bars by 10%
  {:cmd:}  EURUSD1.hst / 0.9 --from=2022.01.03 --to='2022.01.06 17:55'      # scale down a bar range by 10%

DOCOPT;

    /** @var string - history file to process */
    protected $file;

    /** @var string */
    protected $operation;

    /** @var double */
    protected $operand;

    /** @var int */
    protected $from;

    /** @var int */
    protected $to;

    /** @var bool */
    protected $fixBars;


    /**
     * Validate the command line arguments logically (the call syntax is already validated).
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

        // FILE
        if (!is_file($file = $input->getArgument('FILE'))) return $error(1, 'error: file "'.$file.'" not found');
        $this->file = $file;

        // operator
        if     ($input->getArgument('+')) $this->operation = '+';
        elseif ($input->getArgument('-')) $this->operation = '-';
        elseif ($input->getArgument('*')) $this->operation = '*';
        elseif ($input->getArgument('/')) $this->operation = '/';
        else                              return $error(1, 'error: invalid scaling operator');

        // <value> i.e. the operand
        if (!is_numeric($value = $input->getArgument('<value>'))) return $error(1, 'error: non-numeric <value>');
        $value = (float) $value;
        if (!$value)                                              return $error(1, 'error: invalid <value> (zero)');
        $this->operand = (float) $value;

        // --from <datetime>
        $from = $input->getOption('--from');
        if (is_string($from)) {
            if ($date = DateTime::createFromFormat('Y.m.d H:i T', $from.' GMT')) {
                $this->from = $date->getTimestamp();
            }
            elseif ($date = DateTime::createFromFormat('Y.m.d H:i T', $from.' 00:00 GMT')) {
                $this->from = $date->getTimestamp();
            }
            else {
                return $error(1, 'error: invalid --from time');
            }
        }

        // --to <datetime>
        $to = $input->getOption('--to');
        if (is_string($to)) {
            if ($date = DateTime::createFromFormat('Y.m.d H:i T', $to.' GMT')) {
                $this->to = $date->getTimestamp();
            }
            elseif ($date = DateTime::createFromFormat('Y.m.d H:i T', $to.' 00:00 GMT')) {
                $this->to = $date->getTimestamp() + 1*DAY;
            }
            else {
                return $error(1, 'error: invalid --to time');
            }
        }
        if (is_string($from) && is_string($to)) {
            if ($this->from > $this->to) return $error(1, 'error: invalid --from/--to time range');
        }

        // --fix-bars
        $this->fixBars = (bool) $input->getOption('--fix-bars');
        return 0;
    }


    /**
     * @param  Input  $input
     * @param  Output $output
     *
     * @return int - execution status (0 for success)
     */
    protected function execute(Input $input, Output $output): int {
        $stdout    = function($message)          use ($output) { $output->out($message); };
        $error     = function($status, $message) use ($output) { $output->error($message); return $status; };
        $file      = $this->file;
        $operation = $this->operation;
        $operand   = $this->operand;
        $from      = $this->from ?: 0;
        $to        = $this->to ?: PHP_INT_MAX;
        $fixBars   = $this->fixBars;

        // open history file and read header
        $fileSize = filesize($file);
        if ($fileSize < HistoryHeader::SIZE) return $error(1, 'error: invalid or unknown file format (file size < min. size of '.HistoryHeader::SIZE.')');
        $hFile = fopen($file, 'r+b');
        try {
            $header = HistoryHeader::fromStruct(fread($hFile, HistoryHeader::SIZE));
        }
        catch (MetaTraderException $ex) {
            if (strStartsWith($ex->getMessage(), 'version.unsupported')) return $error(1, 'error: unsupported history format in "'.$file.'"');
            throw $ex;
        }
        $version   = $header->getFormat();
        $digits    = $header->getDigits();
        $barSize   = $version==400 ? MT4::HISTORY_BAR_400_SIZE : MT4::HISTORY_BAR_401_SIZE;
        $barFormat = MT4::BAR_getUnpackFormat($version);
        $bars      = ($fileSize-HistoryHeader::SIZE) / $barSize;
        if (!is_int($bars))  return $error(1, 'error: invalid size of file "'.$file.'" (EOF is not on a bar boundary)');
        if ($version != 400) return $error(1, 'error: processing of history format '.$version.' not yet implemented');

        // lookup start bar and set initial file pointer position
        $startbar = $this->findStartbar($hFile, $bars, $barSize, $barFormat, $from);
        fseek($hFile, HistoryHeader::SIZE + $startbar*$barSize);

        // transformation helper
        $transform = function($value, &$modified) use ($operation, $operand) {
            $modified = true;

            switch ($operation) {
                case '+': return $value + $operand;
                case '-': return $value - $operand;
                case '*': return $value * $operand;
                case '/': return $value / $operand;
            }
            throw new InvalidValueException('invalid parameter $operation: '.$operation);
        };

        // iterate over remaining bars and transform data
        for ($i=$startbar, $n=0; $i < $bars; $i++) {
            $bar = unpack($barFormat, fread($hFile, $barSize)); // read bar
            $modified = false;

            if ($bar['time'] < $from) continue;
            if ($bar['time'] >= $to)  break;

            $bar['open' ] = $transform($bar['open' ], $modified);   // transform data
            $bar['high' ] = $transform($bar['high' ], $modified);
            $bar['low'  ] = $transform($bar['low'  ], $modified);
            $bar['close'] = $transform($bar['close'], $modified);

            if ($fixBars) {
                $bar['high'] = max($bar['open'], $bar['high'], $bar['low'], $bar['close']);
                $bar['low' ] = min($bar['open'], $bar['high'], $bar['low'], $bar['close']);
            }

            fseek($hFile, -$barSize, SEEK_CUR);                 // write transformed bar
            MT4::writeHistoryBar400($hFile, $digits, $bar['time'], $bar['open'], $bar['high'], $bar['low'], $bar['close'], $bar['ticks']);
            $modified && $n++;
        }
        fclose($hFile);

        $stdout($file.': modified '.$n.' bar'.pluralize($n));
        return 0;
    }


    /**
     * Fast lookup of the processing start bar (not the transformation start bar).
     *
     * @param  resource $hFile     - file handle
     * @param  int      $bars      - number of bars in the file
     * @param  int      $barSize   - bar size
     * @param  string   $barFormat - bar unpack format
     * @param  int      $from      - time to lookup
     *
     * @return int - start bar offset with the oldest bar having offset = 0
     */
    private function findStartbar($hFile, $bars, $barSize, $barFormat, $from) {
        if ($bars < 2 && !$from) return 0;

        $iFirstBar = 0;
        fseek($hFile, HistoryHeader::SIZE + $iFirstBar*$barSize);
        $firstBar = unpack($barFormat, fread($hFile, $barSize));

        $iLastBar = $bars-1;
        fseek($hFile, HistoryHeader::SIZE + $iLastBar*$barSize);
        $lastBar = unpack($barFormat, fread($hFile, $barSize));

        while (true) {
            $bars = $iLastBar - $iFirstBar + 1;                     // number of remaining bars

            if ($firstBar['time'] > $from) return $iFirstBar;
            if ($lastBar['time'] <= $from) return $iLastBar;
            if ($bars <= 2)                return $iLastBar;

            $half = (int) ceil($bars/2);                            // narrow the range
            $iMidBar = $iFirstBar + $half - 1;
            fseek($hFile, HistoryHeader::SIZE + $iMidBar*$barSize);
            $midBar = unpack($barFormat, fread($hFile, $barSize));
            if ($midBar['time'] <= $from) { $firstBar = $midBar; $iFirstBar = $iMidBar; }
            else                          { $lastBar  = $midBar; $iLastBar  = $iMidBar; }
        }
    }
}
