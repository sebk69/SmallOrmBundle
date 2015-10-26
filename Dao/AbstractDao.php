<?php
/**
 * This file is a part of SebkSmallOrmBundle
 * Copyright 2015 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmBundle\Dao;

use Sebk\SmallOrmBundle\Database\Connection;
use Sebk\SmallOrmBundle\Factory\Dao;
use Sebk\SmallOrmBundle\QueryBuilder\QueryBuilder;

/**
 * Abstract class to provide base dao features
 */
abstract class AbstractDao
{
    protected $connection;
    protected $daoFactory;
    protected $modelNamespace;
    private $modelName;
    private $modelBundle;
    private $dbTableName;
    private $primaryKeys;
    private $fields;
    private $toOne  = array();
    private $toMany = array();

    public function __construct(Connection $connection, Dao $daoFactory,
                                $modelNamespace, $modelName, $modelBundle)
    {
        $this->connection     = $connection;
        $this->daoFactory     = $daoFactory;
        $this->modelNamespace = $modelNamespace;
        $this->modelName      = $modelName;
        $this->modelBundle    = $modelBundle;

        $this->build();
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * @param string $name
     * @return \Sebk\SmallOrmBundle\Database\AbstractDao
     */
    protected function setModelName($name)
    {
        $this->modelName = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getDbTableName()
    {
        return $this->dbTableName;
    }

    /**
     * @param string $name
     * @return \Sebk\SmallOrmBundle\Database\AbstractDao
     */
    protected function setDbTableName($name)
    {
        $this->dbTableName = $name;

        return $this;
    }

    /**
     * @param string $dbFieldName
     * @param string $modelFieldName
     */
    protected function addPrimaryKey($dbFieldName, $modelFieldName)
    {
        $this->primaryKeys[] = new Field($dbFieldName, $modelFieldName);

        return $this;
    }

    /**
     * @param string $dbFieldName
     * @param string $modelFieldName
     */
    protected function addField($dbFieldName, $modelFieldName)
    {
        $this->fields[] = new Field($dbFieldName, $modelFieldName);

        return $this;
    }

    /**
     * Build definition of table
     * Must be defined in model acheviment
     */
    abstract protected function build();

    /**
     * Get primary keys definition
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * Get fields difinitions
     * @param boolean $withIds
     * @return array
     */
    public function getFields($withIds = true)
    {
        $result = array();
        if ($withIds == true) {
            $result = $this->primaryKeys;
        }

        $result = array_merge($result, $this->fields);

        return $result;
    }

    /**
     * Create new model
     * @return \Sebk\SmallOrmBundle\Dao\modelClass
     */
    public function newModel()
    {
        $modelClass = $this->modelNamespace."\\".$this->modelName;

        $primaryKeys = array();
        foreach ($this->primaryKeys as $primaryKey) {
            $primaryKeys[] = lcfirst($primaryKey->getModelName());
        }

        $fields = array();
        foreach ($this->fields as $field) {
            $fields[] = lcfirst($field->getModelName());
        }

        $toOnes = array();
        foreach ($this->toOne as $toOneAlias => $toOne) {
            $toOnes[] = lcFirst($toOneAlias);
        }

        $toManys = array();
        foreach ($this->toMany as $toManyAlias => $toMany) {
            $toManys[] = lcFirst($toManyAlias);
        }

        return new $modelClass($this->modelName, $this->modelBundle,
            $primaryKeys, $fields, $toOnes, $toManys);
    }

    /**
     * Create query builder object with base model from this dao
     * @param type $alias
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias = null)
    {
        return new QueryBuilder($this, $alias);
    }

    /**
     * Execute sql and get raw result
     * @param QueryBuilder $query
     * @return array
     */
    public function getRawResult(QueryBuilder $query)
    {
        return $this->connection->execute($query->getSql(),
                $query->getParameters());
    }

    /**
     * Has field
     * @param string $fieldName
     * @return boolean
     */
    public function hasField($fieldName)
    {
        foreach ($this->getFields() as $field) {
            if ($field->getModelName($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get field object
     * @param string $fieldName
     * @return Field
     * @throws DaoException
     */
    public function getField($fieldName)
    {
        foreach ($this->getFields() as $field) {
            if ($field->getModelName() == $fieldName) {
                return $field;
            }
        }

        throw new DaoException("Field '$fieldName' not found");
    }

    /**
     * Add a relation to model
     * @param \Sebk\SmallOrmBundle\Dao\Relation $relation
     * @return \Sebk\SmallOrmBundle\Dao\AbstractDao
     * @throws DaoException
     */
    public function addRelation(Relation $relation)
    {
        if ($relation instanceof ToOneRelation) {
            $this->toOne[$relation->getAlias()] = $relation;

            return $this;
        }

        if ($relation instanceof ToManyRelation) {
            $this->toMany[$relation->getAlias()] = $relation;

            return $this;
        }

        throw new DaoException("Unknonw relation type");
    }

    /**
     * Get result for a query
     * @param QueryBuilder $query
     * @return array
     */
    public function getResult(QueryBuilder $query)
    {
        $records = $this->getRawResult($query);

        return $this->buildResult($query, $records);
    }

    /**
     * Convert resultset to objects
     * @param QueryBuilder $query
     * @param array $records
     * @param string $alias
     * @return array
     */
    protected function buildResult(QueryBuilder $query, $records, $alias = null)
    {
        if ($alias === null) {
            $alias = $query->getRelation()->getAlias();
        }

        $result = array();

        $group = array();
        foreach ($records as $record) {
            $ids = $this->extractPrimaryKeysOfRecord($query, $alias, $record);
            foreach ($ids as $idName => $idValue) {
                if (count($group) && $savedIds[$idName] != $idValue) {
                    $result[] = $this->populate($query, $alias, $group);
                    $group    = array();
                }

                $group[] = $record;
            }

            $savedIds = $ids;
        }
        if (count($group)) {
            $result[] = $this->populate($query, $alias, $group);
        }

        return $result;
    }

    /**
     *
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $records
     * @return Model
     */
    protected function populate(QueryBuilder $query, $alias, $records)
    {
        $model  = $this->newModel();
        $fields = $this->extractFieldsOfRecord($query, $alias, $records[0]);

        foreach ($fields as $property => $value) {
            $method = "set".$property;
            $model->$method($value);
        }

        foreach ($query->getChildRelationsForAlias($alias) as $join) {
            if ($join->getDaoRelation() instanceof ToOneRelation) {
                $method       = "set".$join->getDaoRelation()->getAlias();
                $toOneObjects = $join->getDaoRelation()->getDao()->buildResult($query,
                    $records, $join->getAlias());
                $model->$method($toOneObjects[0]);
            }

            if ($join->getDaoRelation() instanceof ToManyRelation) {
                $method      = "set".$join->getDaoRelation()->getAlias();
                $toOneObject = $join->getDaoRelation()->getDao()->buildResult($query,
                    $records, $join->getAlias());
                $model->$method($toOneObject);
            }
        }

        $model->setOriginalPrimaryKeys();
        $model->fromDb = true;

        return $model;
    }

    /**
     * Extract ids of this model of record
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $record
     * @return array
     * @throws DaoException
     */
    private function extractPrimaryKeysOfRecord(QueryBuilder $query, $alias,
                                                $record)
    {
        $queryRelation = $query->getRelation($alias);

        $result = array();
        foreach ($this->getPrimaryKeys() as $field) {
            if (array_key_exists($queryRelation->getFieldAliasForSql($field),
                    $record)) {
                $result[$field->getModelName()] = $record[$queryRelation->getFieldAliasForSql($field)];
            } else {
                throw new DaoException("Record not match query");
            }
        }

        return $result;
    }

    /**
     * Extract fields of this model of record
     * @param QueryBuilder $query
     * @param string $alias
     * @param array $record
     * @return array
     * @throws DaoException
     */
    private function extractFieldsOfRecord(QueryBuilder $query, $alias, $record)
    {
        $queryRelation = $query->getRelation($alias);

        $result = array();
        foreach ($this->getFields() as $field) {
            if (array_key_exists($queryRelation->getFieldAliasForSql($field),
                    $record)) {
                $result[$field->getModelName()] = $record[$queryRelation->getFieldAliasForSql($field)];
            } else {
                throw new DaoException("Record not match query");
            }
        }

        return $result;
    }

    /**
     *
     * @param array $keys
     * @param string $toModel
     * @param string $toBundle
     * @return \Sebk\SmallOrmBundle\Dao\AbstractDao
     * @throws DaoException
     */
    public function addToOne($alias, $keys, $toModel, $toBundle = null)
    {
        if ($toBundle === null) {
            $toBundle = $this->modelBundle;
        }

        foreach ($keys as $thisKey => $otherKey) {
            try {
                $this->getField($thisKey);
            } catch (DaoException $e) {
                throw new DaoException("The field '$thisKey' of relation to '$toModel' of bundle '$toBundle' does not exists in '$this->modelName'");
            }
        }

        $this->toOne[$alias] = new ToOneRelation($toBundle, $toModel, $keys,
            $this->daoFactory, $alias);

        return $this;
    }

    /**
     *
     * @param array $keys
     * @param string $toModel
     * @param string $toBundle
     * @return \Sebk\SmallOrmBundle\Dao\AbstractDao
     * @throws DaoException
     */
    public function addToMany($alias, $keys, $toModel, $toBundle = null)
    {
        if ($toBundle === null) {
            $toBundle = $this->modelBundle;
        }

        foreach ($keys as $thisKey => $otherKey) {
            try {
                $this->getField($thisKey);
            } catch (DaoException $e) {
                throw new DaoException("The field '$thisKey' of relation to '$toModel' of bundle '$toBundle' does not exists in '$this->modelName'");
            }
        }

        $this->toMany[$alias] = new ToManyRelation($toBundle, $toModel, $keys,
            $this->daoFactory, $alias);

        return $this;
    }

    /**
     * Get relation
     * @param string $alias
     * @return Relation
     * @throws DaoException
     */
    public function getRelation($alias)
    {
        if (array_key_exists($alias, $this->toOne)) {
            return $this->toOne[$alias];
        }

        if (array_key_exists($alias, $this->toMany)) {
            return $this->toMany[$alias];
        }

        throw new DaoException("Relation '$alias' does not exists in '$this->modelName' of bundle '$this->modelBundle'");
    }

    /**
     * Insert model in database
     * @param \Sebk\SmallOrmBundle\Dao\Model $model
     * @return \Sebk\SmallOrmBundle\Dao\AbstractDao
     */
    public function insert(Model $model)
    {
        $sql    = "INSERT INTO ".$this->connection->getDatabase().".".$this->dbTableName." VALUES(";
        $fields = $model->asArray(false, true);
        foreach ($fields as $key => $val) {
            $queryFields[$key] = ":$key";
        }
        $sql .= implode(", ", $queryFields);
        $sql .= ");";

        $this->connection->execute($sql, $fields);

        if ($this->connection->lastInsertId() !== null) {
            foreach ($model->getPrimaryKeys() as $key => $value) {
                if ($value === null) {
                    $method = "set".$key;
                    $model->$method($this->connection->lastInsertId());
                }
            }
        }

        $model->fromDb  = true;
        $model->altered = false;

        return $this;
    }

    /**
     * Insert a record
     * @param string $modelName
     * @return string
     * @throws DaoException
     */
    public function getDbNameFromModelName($modelName)
    {
        foreach ($this->getFields() as $field) {
            if ($modelName == $field->getModelName()) {
                return $field->getDbName();
            }
        }

        throw new DaoException("Field '$modelName' does not exists in '$this->modelBundle' '$this->modelName' model");
    }

    /**
     * Update a record
     * @param \Sebk\SmallOrmBundle\Dao\Model $model
     * @return \Sebk\SmallOrmBundle\Dao\AbstractDao
     * @throws DaoException
     */
    public function update(Model $model)
    {
        if (!$model->fromDb) {
            throw new DaoException("Try update a record not from db from '$this->modelBundle' '$this->modelName' model");
        }
        $parms = array();

        $sql    = "UPDATE ".$this->connection->getDatabase().".".$this->dbTableName." set ";
        $fields = $model->asArray(false, true);
        foreach ($fields as $key => $val) {
            $queryFields[$key] = $this->getDbNameFromModelName($key)." = :$key";
            $parms[$key] = $val;
        }
        $sql .= implode(", ", $queryFields);
        $sql .= " WHERE ";
        $conds = array();
        foreach ($model->getOriginalPrimaryKeys() as $originalPk => $originalValue) {
            $conds[] = $this->getDbNameFromModelName($originalPk)." = :".$originalPk."OriginalPk";
            $parms[$originalPk."OriginalPk"] = $originalValue;
        }
        $sql .= implode(" AND ", $conds);

        var_dump($sql);

        $this->connection->execute($sql, $parms);

        if ($this->connection->lastInsertId() !== null) {
            foreach ($model->getPrimaryKeys() as $key => $value) {
                if ($value === null) {
                    $method = "set".$key;
                    $model->$method($this->connection->lastInsertId());
                }
            }
        }

        $model->fromDb  = true;
        $model->altered = false;

        return $this;
    }

    /**
     * Persist a record
     * @param \Sebk\SmallOrmBundle\Dao\Model $model
     */
    public function persist(Model $model)
    {
        if($model->fromDb) {
            $this->update($model);
        } else {
            $this->insert($model);
        }
    }

    /**
     *
     * @param stdClass $stdClass
     * @param boolean $setOriginalKeys
     * @return \Sebk\SmallOrmBundle\Dao\Model
     */
    public function makeModelFromStdClass($stdClass, $setOriginalKeys = false)
    {
        $model = $this->newModel();

        foreach($stdClass as $prop => $value) {
            $method = "set".$prop;
            $model->$method($value);
        }

        return $model;
    }
}