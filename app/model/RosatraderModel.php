<?php
declare(strict_types=1);

namespace rosasurfer\rt\model;

use rosasurfer\ministruts\db\orm\PersistableObject;

/**
 * Provides common functionality for all model classes of the project.
 *
 * @method ?int getId() Return the id (primary key) of the instance.
 */
abstract class RosatraderModel extends PersistableObject
{
    /** @var ?int - primary key */
    protected $id = null;

    /** @var ?string - creation time */
    protected $created = null;

    /** @var ?string - last modification time */
    protected $modified = null;


    /**
     * Return the creation time of the instance.
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return ?string - creation time
     */
    public function getCreated($format = 'Y-m-d H:i:s') {
        if (!isset($this->created) || $format=='Y-m-d H:i:s') {
            return $this->created;
        }
        return date($format, strtotime($this->created));
    }


    /**
     * Return the last modification time of the instance.
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return ?string - last modification time or NULL if the instance hasn't been modified yet
     */
    public function getModified($format = 'Y-m-d H:i:s') {
        if (!isset($this->modified) || $format=='Y-m-d H:i:s') {
            return $this->modified;
        }
        return date($format, strtotime($this->modified));
    }


    /**
     * {@inheritDoc}
     *
     * Update the version field as this is not yet automated by the ORM.
     */
    protected function beforeUpdate(): bool {
        $this->modified = gmdate('Y-m-d H:i:s');
        return parent::beforeUpdate();
    }
}
