<?php
namespace rosasurfer\rost\synthetic\custom;

use rosasurfer\exception\IllegalTypeException;

use rosasurfer\rost\FXT;
use rosasurfer\rost\model\RosaSymbol;
use rosasurfer\rost\synthetic\AbstractSynthesizer;
use rosasurfer\rost\synthetic\SynthesizerInterface as Synthesizer;


/**
 * USDLFX calculator
 *
 * A {@link Synthesizer} for calculating the "LiteForex US Dollar index".
 *
 * Formula: USDLFX = \sqrt[7]{\frac{USDCAD * USDCHF * USDJPY}{AUDUSD * EURUSD * GBPUSD}}
 */
class USDLFX extends AbstractSynthesizer {


    /** @var string[] */
    protected $components = ['AUDUSD', 'EURUSD', 'GBPUSD', 'USDCAD', 'USDCHF', 'USDJPY'];


    /**
     * {@inheritdoc}
     */
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function calculateQuotes($day) {
        if (!is_int($day)) throw new IllegalTypeException('Illegal type of parameter $day: '.getType($day));

        $pairs = [];
        foreach ($this->components as $name) {
            /** @var RosaSymbol $pair */
            $pair = RosaSymbol::dao()->getByName($name);
            $pairs[$pair->getName()] = $pair;
        }

        // on $day == 0 start with the oldest available history of all components
        if (!$day) {
            /** @var RosaSymbol $pair */
            foreach ($pairs as $pair) {
                $historyStart = (int) $pair->getHistoryM1Start('U');    // 00:00 FXT of the first stored day
                if (!$historyStart) {
                    echoPre('[Error]   '.$this->symbol->getName().'  required M1 history for '.$pair->getName().' not available');
                    return [];                                          // no history stored
                }
                $day = max($day, $historyStart);
            }
            echoPre('[Info]    '.$this->symbol->getName().'  common M1 history starts at '.gmDate('D, d-M-Y', $day));
        }
        if (!$this->symbol->isTradingDay($day))                         // skip non-trading days
            return [];

        // load history for the specified day
        $quotes = [];
        foreach ($pairs as $name => $pair) {
            $quotes[$name] = $pair->getHistoryM1($day);
        }

        // calculate quotes
        echoPre('[Info]    '.$this->symbol->getName().'  calculating M1 quotes for '.gmDate('D, d-M-Y', $day));
        $AUDUSD = $quotes['AUDUSD'];
        $EURUSD = $quotes['EURUSD'];
        $GBPUSD = $quotes['GBPUSD'];
        $USDCAD = $quotes['USDCAD'];
        $USDCHF = $quotes['USDCHF'];
        $USDJPY = $quotes['USDJPY'];

        $digits = $this->symbol->getDigits();
        $point  = $this->symbol->getPoint();
        $bars   = [];

        // USDLFX = \sqrt[7]{\frac{USDCAD * USDCHF * USDJPY}{AUDUSD * EURUSD * GBPUSD}}
        foreach ($AUDUSD as $i => $bar) {
            $audusd = $AUDUSD[$i]['open'];
            $eurusd = $EURUSD[$i]['open'];
            $gbpusd = $GBPUSD[$i]['open'];
            $usdcad = $USDCAD[$i]['open'];
            $usdchf = $USDCHF[$i]['open'];
            $usdjpy = $USDJPY[$i]['open'];
            $open   = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd), 1/7);
            $open   = round($open, $digits);
            $iOpen  = (int) round($open/$point);

            $audusd = $AUDUSD[$i]['close'];
            $eurusd = $EURUSD[$i]['close'];
            $gbpusd = $GBPUSD[$i]['close'];
            $usdcad = $USDCAD[$i]['close'];
            $usdchf = $USDCHF[$i]['close'];
            $usdjpy = $USDJPY[$i]['close'];
            $close  = pow(($usdcad/$audusd) * ($usdchf/$eurusd) * ($usdjpy/$gbpusd), 1/7);
            $close  = round($close, $digits);
            $iClose = (int) round($close/$point);

            $bars[$i]['time' ] = $bar['time'];
            $bars[$i]['open' ] = $open;
            $bars[$i]['high' ] = $iOpen > $iClose ? $open : $close;                 // no min()/max(): This is a massive loop and
            $bars[$i]['low'  ] = $iOpen < $iClose ? $open : $close;                 // every single function call slows it down.
            $bars[$i]['close'] = $close;
            $bars[$i]['ticks'] = $iOpen==$iClose ? 1 : (abs($iOpen-$iClose) << 1);
        }
        return $bars;
    }
}
