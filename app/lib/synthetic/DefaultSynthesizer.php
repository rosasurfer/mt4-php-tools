<?php
namespace rosasurfer\rost\synthetic;


/**
 * Synthesizer
 *
 * A class for processing calculations on synthetic instruments. A synthetic instrument is made of components and a defined
 * relation between them (a math formula). Components and formula are stored with each synthetic instrument in text form.
 * The Synthesizer parses and evaluates these descriptions and calculates instrument quotes based on it. This makes it
 * possible to calculate quotes of on-the-fly generated synthetic instruments based on user input provided at runtime.
 *
 * Calculation can be considerably speed-up by providing an instrument-specific {@link CalculatorInterface} implementation.
 * If such an instrument-specific calculator is found it replaces the textual definitions and the Synthesizer passes
 * calculation on to an instance of that class.
 */
class DefaultSynthesizer extends AbstractSynthesizer {


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
        return [];
    }
}
