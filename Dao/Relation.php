<?php
/**
 * This file is a part of SebkSmallOrmBundle
 * Copyrigtht 2015 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmBundle\Dao;

use Sebk\SmallOrmBundle\Factory\Dao;

/**
 * Relation to another model
 */
class Relation
{
    protected $keys;
    protected $modelBundle;
    protected $modelName;
    protected $daoFactory;
    protected $alias;

    /**
     * Contruct relation
     * @param string $modelBundle
     * @param string $modelName
     * @param string $alias
     * @param Dao $daoFactory
     */
    public function __construct($modelBundle, $modelName, $relationKeys,
                                Dao $daoFactory, $alias)
    {
        $this->modelBundle = $modelBundle;
        $this->modelName   = $modelName;
        $this->daoFactory  = $daoFactory;
        $this->keys        = $relationKeys;
        $this->alias       = $alias;
    }

    /**
     * @return Dao
     */
    public function getDao()
    {
        return $this->daoFactory->get($this->modelBundle, $this->modelName);
    }

    /**
     * 
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @return array
     */
    public function getKeys()
    {
        return $this->keys;
    }
}