<?php
namespace rosasurfer\rt\console;

use rosasurfer\console\Command;


/**
 * TestCommand
 */
class TestCommand extends Command {


    /**
     * {@inheritdoc}
     *
     * @return int - execution status code: 0 (zero) for "success"
     */
    public function execute($input) {
        echo 'Hello world!';
        return 0;
    }
}
