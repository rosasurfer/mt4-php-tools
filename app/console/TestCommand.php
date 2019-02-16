<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;
use rosasurfer\console\input\Input;


/**
 * TestCommand
 */
class TestCommand extends Command {


    /** @var string */
    const DOCOPT = <<<'DOCOPT'
Process FILE and optionally apply correction to either left-hand or right-hand side.

Usage: rt command command <arg1> ARG2
       rt [-rh] [--quiet=<x>...] [FILE...]
       rt (--left | --right) CORRECTION FILE

Arguments:
  FILE         optional input file
  CORRECTION   correction angle, needs FILE, --left or --right to be present

Options:
  -h --help
  -q --quiet=<x>  quiet mode
  -r --report     make report
  --left          use left-hand side
  --right         use right-hand side

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
    protected function execute(Input $input) {
        echoPre($input->getDocoptResult());
        //var_dump($input->getDocoptResult());
        return 0;
    }
}
