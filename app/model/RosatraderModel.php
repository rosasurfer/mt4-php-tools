<?php
namespace rosasurfer\rt\model;

use rosasurfer\db\orm\PersistableObject;


/**
 * Provides common functionality for all project model classes.
 *
 * @method int getId() Return the id (primary key) of the instance.
 */
abstract class RosatraderModel extends PersistableObject {


    /** @var int - primary key */
    protected $id;

    /** @var string - creation time */
    protected $created;

    /** @var string - last modification time */
    protected $modified;


    /**
     * Return the creation time of the instance.
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - creation time
     */
    public function getCreated($format = 'Y-m-d H:i:s') {
        if (!isset($this->created) || $format=='Y-m-d H:i:s')
            return $this->created;
        return date($format, strtotime($this->created));
    }


    /**
     * Return the last modification time of the instance.
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string|null - last modification time or NULL if the instance hasn't been modified yet
     */
    public function getModified($format = 'Y-m-d H:i:s') {
        if (!isset($this->modified) || $format=='Y-m-d H:i:s')
            return $this->modified;
        return date($format, strtotime($this->modified));
    }


    /**
     * Update the version field as this is not yet automated by the ORM.
     *
     * {@inheritdoc}
     */
    protected function beforeUpdate() {
        $this->modified = gmdate('Y-m-d H:i:s');
        return parent::beforeUpdate();
    }
}
