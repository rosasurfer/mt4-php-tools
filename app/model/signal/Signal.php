<?php
namespace rosasurfer\rost\model\signal;

use rosasurfer\db\orm\PersistableObject;
use rosasurfer\exception\IllegalTypeException;


/**
 * Signal
 *
 * @method int              getId()              Return the signal's id (primary key).
 * @method string           getProvider()        Return the signal's provider type (enum).
 * @method string           getProviderId()      Return the signal's provider id.
 * @method string           getName()            Return the signal's name.
 * @method string           getAlias()           Return the signal's alias.
 * @method string           getAccountCurrency() Return the signal's account currency.
 * @method OpenPosition[]   getOpenPositions()   Return the signal's open positions.
 * @method ClosedPosition[] getClosedPositions() Return the signal's closed positions.
 */
class Signal extends PersistableObject {


    /** @var int - primary key */
    protected $id;

    /** @var string - time of creation */
    protected $created;

    /** @var string - time of last modification */
    protected $version;

    /** @var string */
    protected $provider;

    /** @var string */
    protected $providerId;

    /** @var string */
    protected $name;

    /** @var string */
    protected $alias;

    /** @var string */
    protected $accountCurrency;

    /** @var OpenPosition[] [transient] */
    protected $openPositions;

    /** @var ClosedPosition[] [transient] */
    protected $closedPositions;


    /**
     * Return the creation time of the instance.
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - formatted creation time
     */
    public function getCreated($format = 'Y-m-d H:i:s')  {
        if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
        if ($format == 'Y-m-d H:i:s')
            return $this->created;
        return date($format, strToTime($this->created));
    }


    /**
     * Return the version of the instance (last modification time).
     *
     * @param  string $format [optional] - format as used by date($format, $timestamp)
     *
     * @return string - formatted last modification time
     */
    public function getVersion($format = 'Y-m-d H:i:s')  {
        if (!is_string($format)) throw new IllegalTypeException('Illegal type of parameter $format: '.getType($format));
        if ($format == 'Y-m-d H:i:s')
            return $this->version;
        return date($format, strToTime($this->version));
    }


    /**
     * Update the version field as this is not yet automated by the ORM.
     *
     * {@inheritdoc}
     */
    protected function beforeUpdate() {
        $this->version = date('Y-m-d H:i:s');
        return true;
    }
}
