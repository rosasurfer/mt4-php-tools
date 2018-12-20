<?php
namespace rosasurfer\rsx\model;


/**
 * Represents a Dukascopy symbol.
 *
 * @method string     getName()            Return the symbol name, i.e. the actual symbol.
 * @method int        getDigits()          Return the number of fractional digits of symbol prices.
 * @method string     getTickHistoryFrom() Return the start time of the available tick history (FXT).
 * @method string     getTickHistoryTo()   Return the end time of the available tick history (FXT).
 * @method string     getM1HistoryFrom()   Return the start time of the available M1 history (FXT).
 * @method string     getM1HistoryTo()     Return the end time of the available M1 history (FXT).
 * @method RosaSymbol getRosaSymbol()      Return the Rosatrader symbol this Dukascopy symbol is mapped to.
 */
class DukascopySymbol extends RosatraderModel {


    /** @var string - symbol name */
    protected $name;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var string - starttime of the available tick history (FXT) */
    protected $tickHistoryFrom;

    /** @var string - endtime of the available tick history (FXT) */
    protected $tickHistoryTo;

    /** @var string - starttime of the available M1 history (FXT) */
    protected $m1HistoryFrom;

    /** @var string - endtime of the available M1 history (FXT) */
    protected $m1HistoryTo;

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;
}
