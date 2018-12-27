<?php
namespace rosasurfer\rost\model;

use rosasurfer\exception\UnimplementedFeatureException;
use rosasurfer\rost\synthetic\Synthesizer;

use function rosasurfer\rost\fxtTime;


/**
 * Represents a Rosatrader symbol.
 *
 * @method string          getType()            Return the instrument type (forex|metals|synthetic).
 * @method string          getName()            Return the symbol name, i.e. the actual symbol.
 * @method string          getDescription()     Return the symbol description.
 * @method int             getDigits()          Return the number of fractional digits of symbol prices.
 * @method DukascopySymbol getDukascopySymbol() Return the Dukascopy symbol mapped to this RosaTrader symbol.
 */
class RosaSymbol extends RosatraderModel {


    /** @var string */
    const TYPE_FOREX = 'forex';

    /** @var string */
    const TYPE_METAL = 'metals';

    /** @var string */
    const TYPE_SYNTHETIC = 'synthetic';


    /** @var string - instrument type (forex|metals|synthetic) */
    protected $type;

    /** @var string - symbol name */
    protected $name;

    /** @var string - symbol description */
    protected $description;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var string - start time of the available tick history (FXT) */
    protected $tickHistoryFrom;

    /** @var string - end time of the available tick history (FXT) */
    protected $tickHistoryTo;

    /** @var string - start time of the available M1 history (FXT) */
    protected $m1HistoryFrom;

    /** @var string - end time of the available M1 history (FXT) */
    protected $m1HistoryTo;

    /** @var string - start time of the available D1 history (FXT) */
    protected $d1HistoryFrom;

    /** @var string - end time of the available D1 history (FXT) */
    protected $d1HistoryTo;

    /** @var DukascopySymbol [transient] - the Dukascopy symbol mapped to this RosaTrader symbol */
    protected $dukascopySymbol;


    /**
     * Return the start time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time (a returned timestamp is FXT based)
     */
    public function getTickHistoryFrom($format = 'Y-m-d H:i:s') {
        if (!isSet($this->tickHistoryFrom) || $format=='Y-m-d H:i:s')
            return $this->tickHistoryFrom;
        return gmDate($format, strToTime($this->tickHistoryFrom.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - end time (a returned timestamp is FXT based)
     */
    public function getTickHistoryTo($format = 'Y-m-d H:i:s') {
        if (!isSet($this->tickHistoryTo) || $format=='Y-m-d H:i:s')
            return $this->tickHistoryTo;
        return gmDate($format, strToTime($this->tickHistoryTo.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time (a returned timestamp is FXT based)
     */
    public function getM1HistoryFrom($format = 'Y-m-d H:i:s') {
        if (!isSet($this->m1HistoryFrom) || $format=='Y-m-d H:i:s')
            return $this->m1HistoryFrom;
        return gmDate($format, strToTime($this->m1HistoryFrom.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - end time (a returned timestamp is FXT based)
     */
    public function getM1HistoryTo($format = 'Y-m-d H:i:s') {
        if (!isSet($this->m1HistoryTo) || $format=='Y-m-d H:i:s')
            return $this->m1HistoryTo;
        return gmDate($format, strToTime($this->m1HistoryTo.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - start time (a returned timestamp is FXT based)
     */
    public function getD1HistoryFrom($format = 'Y-m-d H:i:s') {
        if (!isSet($this->d1HistoryFrom) || $format=='Y-m-d H:i:s')
            return $this->d1HistoryFrom;
        return gmDate($format, strToTime($this->d1HistoryFrom.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - end time (a returned timestamp is FXT based)
     */
    public function getD1HistoryTo($format = 'Y-m-d H:i:s') {
        if (!isSet($this->d1HistoryTo) || $format=='Y-m-d H:i:s')
            return $this->d1HistoryTo;
        return gmDate($format, strToTime($this->d1HistoryTo.' GMT'));
    }


    /**
     * Whether the symbol is a Forex symbol.
     *
     * @return bool
     */
    public function isForex() {
        return $this->type === self::TYPE_FOREX;
    }


    /**
     * Whether the symbol is a metals symbol.
     *
     * @return bool
     */
    public function isMetal() {
        return $this->type === self::TYPE_METAL;
    }


    /**
     * Whether the symbol is a synthetic symbol.
     *
     * @return bool
     */
    public function isSynthetic() {
        return $this->type === self::TYPE_SYNTHETIC;
    }


    /**
     * Update the symbol's history (atm only M1 is supported).
     *
     * @return bool - success status
     */
    public function updateHistory() {
        if (!$this->isSynthetic()) throw new UnimplementedFeatureException('RosaSymbol::updateHistory() not yet implemented for regular instruments ('.$this->getName().')');

        $updatedTo  = (int) $this->getM1HistoryTo('U');                             // endtime FXT
        $updateFrom = $updatedTo ? $updatedTo - $updatedTo%DAY + 1*DAY : 0;         // 00:00 FXT of the first day to update

        if ($this->isSynthetic()) {
            // request price updates from a synthesizer
            $synthesizer = new Synthesizer($this);
            $availableFrom = (int) $synthesizer->getM1HistoryFrom('U');             // latest start time FXT of all components
            if (!$availableFrom)
                return false(echoPre($this->getName().': history of components of synthetic instrument not available'));
            if ($part = $availableFrom%DAY)
                $availableFrom -= $part - 1*DAY;                                    // 00:00 FXT of the first completely available day
            $updateFrom = max($availableFrom, $updateFrom);
            $today = ($today=fxtTime()) - $today%DAY;                               // 00:00 FXT of the current day

            echoPre('updatedTo: '.$updatedTo.'  availableFrom: '.gmDate('Y-m-d H:i:s', $availableFrom).'  updateFrom: '.gmDate('Y-m-d H:i:s', $updateFrom).'  today: '.gmDate('Y-m-d H:i:s', $today));

            for ($day=$updateFrom; $day < $today; $day+=1*DAY) {
                $bars = $synthesizer->calculateValues($day);
                //store bars
                //store the new updatedTo value
            }
        }
        else {
            // request price updates from a mapped data provider
        }
        return false;
    }
}
