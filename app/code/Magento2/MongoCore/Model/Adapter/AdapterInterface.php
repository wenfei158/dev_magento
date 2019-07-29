<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Model\Adapter;

/**
 * MongoDB Database Adapter Interface
 *
 * @api
 */
interface AdapterInterface
{
    /**
     * Returns information for all collections in this database.
     * @return array|\MongoDB\Model\CollectionInfoIterator
     */
    public function listCollections();

    /**
     * Returns information for all collections in this database.
     * @param $collectionName
     * @param array $options
     * @return array|\MongoDB\Model\CollectionInfoIterator
     */
    public function createCollection($collectionName, array $options = []);

    /**
     * Returns information for all indexes for this collection.
     * @param string $collectionName
     * @return array|\MongoDB\Model\IndexInfoIterator
     */
    public function listIndexes($collectionName);

    /**
     * Create an index for the collection.
     * @param string $collectionName
     * @param array $key
     * @param array $options
     * @return string
     */
    public function createIndex($collectionName, $key, array $options = []): string;

    /**
     * Drop an index from the collection.
     * @param string $collectionName
     * @param string $indexName
     * @param array $options
     * @return array|\MongoDB\Model\BSONDocument
     */
    public function dropIndex($collectionName, $indexName, array $options = []);

    /**
     * Finds a single document matching the query.
     * @param string $collectionName
     * @param array $filter
     * @param array $options
     * @return array|null
     */
    public function findOne($collectionName, $filter = [], array $options = []);

    /**
     * Finds documents matching the query.
     * @param string $collectionName
     * @param array $filter
     * @param array $options
     * @return \MongoDB\Driver\Cursor
     */
    public function find($collectionName, $filter = [], array $options = []);

    /**
     * Count the number of documents that match the filter criteria.
     * @param string $collectionName
     * @param array $filter
     * @param array $options
     * @return integer
     */
    public function count($collectionName, $filter = [], array $options = []): integer;

    /**
     * Insert one document.
     * @param string $collectionName
     * @param array $document
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function insertOne($collectionName, $document, array $options = []);

    /**
     * Insert multiple documents.
     * @param string $collectionName
     * @param array $documents
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function insertMany($collectionName, $documents, array $options = []);

    /**
     * Update at most one document that matches the filter criteria.
     * @param string $collectionName
     * @param array $filter
     * @param array $update
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function updateOne($collectionName, $filter, $update, array $options = []);

    /**
     * Update multiple documents.
     * @param string $collectionName
     * @param array $filter
     * @param array $update
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function updateMany($collectionName, $filter, $update, array $options = []);

    /**
     * Replace at most one document that matches the filter criteria.
     * @param string $collectionName
     * @param array $filter
     * @param array $replacement
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function replaceOne($collectionName, $filter, $replacement, array $options = []);

    /**
     * Deletes at most one document that matches the filter criteria.
     * @param string $collectionName
     * @param array $filter
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function deleteOne($collectionName, $filter, array $options = []);

    /**
     * Delete multiple documents.
     * @param string $collectionName
     * @param array $filter
     * @param array $options
     * @return \MongoDB\InsertOneResult
     */
    public function deleteMany($collectionName, $filter, array $options = []);

    /**
     * Executes multiple write operations.
     * @param string $collectionName
     * @param array $operations
     * @param array $options
     * @return \MongoDB\BulkWriteResult
     */
    public function bulkWrite($collectionName, $operations, array $options = []);
}
