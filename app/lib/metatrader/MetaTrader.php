<?php
namespace rosasurfer\rt\lib\metatrader;

use rosasurfer\file\FileSystem as FS;

use rosasurfer\core\Object;
use rosasurfer\rt\model\RosaSymbol;


/**
 * MetaTrader
 *
 * Functionality for performing MetaTrader related tasks.
 */
class MetaTrader extends Object {


    /**
     * Create a new history set for the given symbol.
     *
     * @param  RosaSymbol $symbol
     * @param  int        $format    [optional] - history format: 400 | 401 (default: 400)
     * @param  string     $directory [optional] - history location (default: configured default server directory)
     *
     * @return HistorySet
     *
     * @see HistoryFile for the different format descriptions
     */
    public function createHistorySet(RosaSymbol $symbol, $format = 400, $directory = null) {
        if (!isset($directory)) {
            $config    = $this->di('config');
            $directory = $config['app.dir.storage'].'/history/mt4/'.$config['rt.metatrader.servername'];
        }
        FS::mkDir($directory);
        return new HistorySet($symbol, $format, $directory);
    }
}
