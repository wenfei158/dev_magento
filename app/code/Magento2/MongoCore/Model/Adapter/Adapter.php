<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Model\Adapter;

use Magento2\MongoCore\Model\Db;

/**
 * MongoDB Adapter
 */
class Adapter implements AdapterInterface
{
    /**
     * @var Db
     */
    private $db;

    /**
     * @param Db $db
     */
    public function __construct(Db $db)
    {
        $this->db = $db->getDb();
    }

    /**
     * @param string $collectionName
     * @param array $options
     * @return \MongoDB\Collection
     */
    private function _selectCollection($collectionName, array $options = [])
    {
        $collection = $this->db->selectCollection($collectionName, $options);
        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function listCollections()
    {
        $collections = $this->db->listCollections();
        return $collections;
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collectionName, array $options = [])
    {
        $collection = $this->db->createCollection($collectionName, $options);
        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function listIndexes($collectionName)
    {
        $indexes = $this->_selectCollection($collectionName)->listIndexes();
        return $indexes;
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex($collectionName, $key, array $options = []): string
    {
        $indexName = $this->_selectCollection($collectionName)->createIndex($key, $options);
        return $indexName;
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex($collectionName, $indexName, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->dropIndex($indexName, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findOne($collectionName, $filter = [], array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->findOne($filter, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function find($collectionName, $filter = [], array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->find($filter, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function count($collectionName, $filter = [], array $options = []): integer
    {
        $result = $this->_selectCollection($collectionName)->count($filter, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function insertOne($collectionName, $document, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->insertOne($document, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function insertMany($collectionName, $documents, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->insertMany($documents, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function updateOne($collectionName, $filter, $update, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->updateOne($filter, $update, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function updateMany($collectionName, $filter, $update, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->updateMany($filter, $update, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replaceOne($collectionName, $filter, $replacement, array $options = [])
    {
        $options['upsert'] = true;
        $result = $this->_selectCollection($collectionName)->replaceOne($filter, $replacement, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteOne($collectionName, $filter, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->deleteOne($filter, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMany($collectionName, $filter, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->deleteMany($filter, $options);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function bulkWrite($collectionName, $operations, array $options = [])
    {
        $result = $this->_selectCollection($collectionName)->bulkWrite($operations, $options);
        return $result;
    }
}
