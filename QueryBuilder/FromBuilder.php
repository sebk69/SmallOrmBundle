<?php

/**
 * This file is a part of SebkSmallOrmBundle
 * Copyrigth 2015 - Sébastien Kus
 */

namespace Sebk\SmallOrmBundle\QueryBuilder;

use Sebk\SmallOrmBundle\Dao\AbstractDao;
use Sebk\SmallOrmBundle\Dao\Field;

/**
 * From definition
 */
class FromBuilder
{
    protected $dao;
    protected $alias;

    /**
     * Constructor
     * @param AbstractDao $dao
     * @param string $alias
     */
    public function __construct(AbstractDao $dao, $alias)
    {
        $this->dao   = $dao;
        $this->alias = $alias;
    }

    /**
     * Get dao
     * @return AbstractDao
     */
    public function getDao()
    {
        return $this->dao;
    }

    /**
     * Get model alias in query
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Get field object for sql
     * @param Field $field
     * @return string
     */
    protected function buildFieldForSql(Field $field)
    {
        return $this->alias.".".$field->getDbName()." AS ".$this->alias."_".$field->getModelName();
    }

    /**
     * Get field identified by string for sql
     * @param string $field
     * @return string
     */
    protected function buildFieldIdentifiedByStringForSql($fieldNameInModel)
    {
        $field = $this->getDao()->getField($fieldNameInModel);
        
        return $this->buildFieldForSql($field);
    }

    /**
     * Get fields as array of select part of sql statement
     * @return array
     */
    public function getFieldsForSqlAsArray()
    {
        $fieldsSelection = array();
        foreach ($this->dao->getFields() as $field) {
            $fieldsSelection[] = $this->buildFieldForSql($field);
        }

        return $fieldsSelection;
    }

    /**
     * Get from part for SQL statement
     * @return string
     */
    public function getFromForSql()
    {
        return $this->dao->getDbTableName()." AS ".$this->alias;
    }
}