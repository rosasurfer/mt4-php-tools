<?php
namespace rosasurfer\rt\model;

use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\InvalidArgumentException;

use rosasurfer\rt\Rost;
use rosasurfer\rt\metatrader\MT4;

use function rosasurfer\rt\isWeekend;


/**
 * Represents a MetaTrader order ticket.
 *
 * @method int    getTicket()      Return the ticket number.
 * @method string getType()        Return the ticket order type.
 * @method float  getLots()        Return the ticket lot size.
 * @method string getSymbol()      Return the ticket symbol.
 * @method float  getOpenPrice()   Return the ticket open price.
 * @method string getOpenTime()    Return the ticket open time (FXT).
 * @method float  getStopLoss()    Return the ticket stop loss price.
 * @method float  getTakeProfit()  Return the ticket take profit price.
 * @method float  getClosePrice()  Return the ticket close price.
 * @method string getCloseTime()   Return the ticket close time (FXT).
 * @method float  getCommission()  Return the ticket commission amount.
 * @method float  getSwap()        Return the ticket swap amount.
 * @method float  getProfit()      Return the ticket gross profit amount.
 * @method int    getMagicNumber() Return the magic number of the ticket.
 * @method string getComment()     Return the ticket comment.
 * @method Test   getTest()        Return the test the ticket belongs to.
 */
class Order extends RosatraderModel {


     /** @var int - ticket number */
    protected $ticket;

    /** @var string - order type: Buy|Sell */
    protected $type;

    /** @var float - lot size */
    protected $lots;

    /** @var string - symbol */
    protected $symbol;

    /** @var float - open price */
    protected $openPrice;

    /** @var string - open time (server timezone) */
    protected $openTime;

    /** @var float - stoploss price */
    protected $stopLoss;

    /** @var float - takeprofit price */
    protected $takeProfit;

    /** @var float - close price */
    protected $closePrice;

    /** @var string - close time (server timezone) */
    protected $closeTime;

    /** @var float - commission */
    protected $commission;

    /** @var float - swap */
    protected $swap;

    /** @var float - gross profit */
    protected $profit;

    /** @var int - magic number */
    protected $magicNumber;

    /** @var string - order comment */
    protected $comment;

    /** @var Test [transient] - the test the order belongs to */
    protected $test;


    /**
     * Create a new Order.
     *
     * @param  Test  $test       - the test the order belongs to
     * @param  array $properties - order properties as parsed from the test's log file
     *
     * @return self
     */
    public static function create(Test $test, array $properties) {
        $order          = new self();
        $order->created = date('Y-m-d H:i:s');
        $order->test    = $test;


        $id = $properties['id'];
        if (!is_int($id))                                     throw new IllegalTypeException('Illegal type of property order.id: '.getType($id));
        if ($id)                                              throw new InvalidArgumentException('Invalid property order.id: '.$id.' (not zero)');
        $order->id = null;

        $ticket = $properties['ticket'];
        if (!is_int($ticket))                                 throw new IllegalTypeException('Illegal type of property order.ticket: '.getType($ticket));
        if ($ticket <= 0)                                     throw new InvalidArgumentException('Invalid property order.ticket: '.$ticket.' (not positive)');
        $order->ticket = $ticket;

        $type = $properties['type'];
        if (!is_int($type))                                   throw new IllegalTypeException('Illegal type of property order['.$ticket.'].type: '.getType($type));
        if (!Rost::isOrderType($type))                        throw new InvalidArgumentException('Invalid property order['.$ticket.'].type: '.$type.' (not an order type)');
        $order->type = Rost::orderTypeDescription($type);

        $lots = $properties['lots'];
        if (!is_float($lots))                                 throw new IllegalTypeException('Illegal type of property order['.$ticket.'].lots: '.getType($lots));
        if ($lots <= 0)                                       throw new InvalidArgumentException('Invalid property order['.$ticket.'].lots: '.$lots.' (not positive)');
        if ($lots != round($lots, 2))                         throw new InvalidArgumentException('Invalid property order['.$ticket.'].lots: '.$lots.' (lot step violation)');
        $order->lots = $lots;

        $symbol = $properties['symbol'];
        if (!is_string($symbol))                              throw new IllegalTypeException('Illegal type of property order['.$ticket.'].symbol: '.getType($symbol));
        if ($symbol != trim($symbol))                         throw new InvalidArgumentException('Invalid property order['.$ticket.'].symbol: "'.$symbol.'" (format violation)');
        if (!strLen($symbol))                                 throw new InvalidArgumentException('Invalid property order['.$ticket.'].symbol: "'.$symbol.'" (length violation)');
        if (strLen($symbol) > MT4::MAX_SYMBOL_LENGTH)         throw new InvalidArgumentException('Invalid property order['.$ticket.'].symbol: "'.$symbol.'" (length violation)');
        $order->symbol = $symbol;

        $openPrice = $properties['openPrice'];
        if (!is_float($openPrice))                            throw new IllegalTypeException('Illegal type of property order['.$ticket.'].openPrice: '.getType($openPrice));
        $openPrice = round($openPrice, 5);
        if ($openPrice <= 0)                                  throw new InvalidArgumentException('Invalid property order['.$ticket.'].openPrice: '.$openPrice.' (not positive)');
        $order->openPrice = $openPrice;

        $openTime = $properties['openTime'];                  // FXT timestamp
        if (!is_int($openTime))                               throw new IllegalTypeException('Illegal type of property order['.$ticket.'].openTime: '.getType($openTime));
        if ($openTime <= 0)                                   throw new InvalidArgumentException('Invalid property order['.$ticket.'].openTime: '.$openTime.' (not positive)');
        if (isWeekend($openTime))                             throw new InvalidArgumentException('Invalid property order['.$ticket.'].openTime: '.$openTime.' (not a weekday)');
        $order->openTime = gmDate('Y-m-d H:i:s', $openTime);

        $stopLoss = $properties['stopLoss'];
        if (!is_float($stopLoss))                             throw new IllegalTypeException('Illegal type of property order['.$ticket.'].stopLoss: '.getType($stopLoss));
        $stopLoss = round($stopLoss, 5);
        if ($stopLoss < 0)                                    throw new InvalidArgumentException('Invalid property order['.$ticket.'].stopLoss: '.$stopLoss.' (not non-negative)');
        $order->stopLoss = !$stopLoss ? null : $stopLoss;

        $takeProfit = $properties['takeProfit'];
        if (!is_float($takeProfit))                           throw new IllegalTypeException('Illegal type of property order['.$ticket.'].takeProfit: '.getType($takeProfit));
        $takeProfit = round($takeProfit, 5);
        if ($takeProfit < 0)                                  throw new InvalidArgumentException('Invalid property order['.$ticket.'].takeProfit: '.$takeProfit.' (not non-negative)');
        $order->takeProfit = !$takeProfit ? null : $takeProfit;

        if ($stopLoss && $takeProfit) {
            if (Rost::isLongOrderType(Rost::strToOrderType($order->type))) {
                if ($stopLoss >= $takeProfit)                 throw new InvalidArgumentException('Invalid properties order['.$ticket.'].stopLoss|takeProfit for LONG order: '.$stopLoss.'|'.$takeProfit.' (mis-match)');
            }
            else if ($stopLoss <= $takeProfit)                throw new InvalidArgumentException('Invalid properties order['.$ticket.'].stopLoss|takeProfit for SHORT order: '.$stopLoss.'|'.$takeProfit.' (mis-match)');
        }

        $closePrice = $properties['closePrice'];
        if (!is_float($closePrice))                           throw new IllegalTypeException('Illegal type of property order['.$ticket.'].closePrice: '.getType($closePrice));
        $closePrice = round($closePrice, 5);
        if ($closePrice < 0)                                  throw new InvalidArgumentException('Invalid property order['.$ticket.'].closePrice: '.$closePrice.' (not non-negative)');
        $order->closePrice = !$closePrice ? null : $closePrice;

        $closeTime = $properties['closeTime'];                // FXT timestamp
        if (!is_int($closeTime))                              throw new IllegalTypeException('Illegal type of property order['.$ticket.'].closeTime: '.getType($closeTime));
        if ($closeTime < 0)                                   throw new InvalidArgumentException('Invalid property order['.$ticket.'].closeTime: '.$closeTime.' (not positive)');
        if      ($closeTime && !$closePrice)                  throw new InvalidArgumentException('Invalid properties order['.$ticket.'].closePrice|closeTime: '.$closePrice.'|'.$closeTime.' (mis-match)');
        else if (!$closeTime && $closePrice)                  throw new InvalidArgumentException('Invalid properties order['.$ticket.'].closePrice|closeTime: '.$closePrice.'|'.$closeTime.' (mis-match)');
        if ($closeTime) {
            if (isWeekend($closeTime))                        throw new InvalidArgumentException('Invalid property order['.$ticket.'].closeTime: '.$closeTime.' (not a weekday)');
            if ($closeTime < $openTime)                       throw new InvalidArgumentException('Invalid properties order['.$ticket.'].openTime|closeTime: '.$openTime.'|'.$closeTime.' (mis-match)');
        }
        $order->closeTime = !$closeTime ? null : gmDate('Y-m-d H:i:s', $closeTime);

        $commission = $properties['commission'];
        if (!is_float($commission))                           throw new IllegalTypeException('Illegal type of property order['.$ticket.'].commission: '.getType($commission));
        $order->commission = round($commission, 2);

        $swap = $properties['swap'];
        if (!is_float($swap))                                 throw new IllegalTypeException('Illegal type of property order['.$ticket.'].swap: '.getType($swap));
        $order->swap = round($swap, 2);

        $profit = $properties['profit'];
        if (!is_float($profit))                               throw new IllegalTypeException('Illegal type of property order['.$ticket.'].profit: '.getType($profit));
        $order->profit = round($profit, 2);

        $magicNumber = $properties['magicNumber'];
        if (!is_int($magicNumber))                            throw new IllegalTypeException('Illegal type of property order['.$ticket.'].magicNumber: '.getType($magicNumber));
        if ($magicNumber < 0)                                 throw new InvalidArgumentException('Invalid property order['.$ticket.'].magicNumber: '.$magicNumber.' (not non-negative)');
        $order->magicNumber = !$magicNumber ? null : $magicNumber;

        $comment = $properties['comment'];
        if (!is_string($comment))                             throw new IllegalTypeException('Illegal type of property order['.$ticket.'].comment: '.getType($comment));
        $comment = trim($comment);
        if (strLen($comment) > MT4::MAX_ORDER_COMMENT_LENGTH) throw new InvalidArgumentException('Invalid property order['.$ticket.'].comment: "'.$comment.'" (length violation)');
        $order->comment = strLen($comment) ? $comment : null;

        return $order;
    }


    /**
     * Whether or not this order ticket represents a position and not a pending order.
     *
     * @return bool
     */
    public function isPosition() {
        return ((strCompareI($this->type, 'Buy') || strCompareI($this->type, 'Sell')) && $this->openTime > 0);
    }


    /**
     * Whether or not this ticket is closed.
     *
     * @return bool
     */
    public function isClosed() {
        return ($this->closeTime > 0);
    }


    /**
     * Whether or not this ticket represents a closed position.
     *
     * @return bool
     */
    public function isClosedPosition() {
        return ($this->isPosition() && $this->isClosed());
    }
}
