<?php
namespace rosasurfer\rt\view;

use rosasurfer\console\Command;


/**
 * TestCommand
 */
class TestCommand extends Command {


    /**
     * {@inheritdoc}
     */
    public function execute($input, $output) {
        echo 'Hello world!';
        return 0;
    }
}
