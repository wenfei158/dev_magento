<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoImportExport\Model\Import\Product\Type\Grouped;

use Magento\Framework\App\ResourceConnection;
use Magento2\MongoCore\Model\Adapter\Adapter;

/**
 * Processing db operations for import entity of grouped product type
 */
class Links extends \Magento\GroupedImportExport\Model\Import\Product\Type\Grouped\Links
{
    /**
     * MongoDB Adapter
     *
     * @var Adapter
     */
    protected $mongoAdapter;

    /**
     * @param Adapter $adapter
     * @param \Magento\Catalog\Model\ResourceModel\Product\Link $productLink
     * @param ResourceConnection $resource
     * @param \Magento\ImportExport\Model\ImportFactory $importFactory
     */
    public function __construct(
        Adapter $adapter,
        \Magento\Catalog\Model\ResourceModel\Product\Link $productLink,
        ResourceConnection $resource,
        \Magento\ImportExport\Model\ImportFactory $importFactory
    ) {
        $this->mongoAdapter = $adapter;
        parent::__construct($productLink, $resource, $importFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function saveLinksData($linksData)
    {
        $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
        $collectionId = \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        $mainTable = $this->productLink->getMainTable();
        $relationTable = $this->productLink->getTable('catalog_product_relation');
        $mongoContent = [];
        // save links and relations
        if ($linksData['product_ids']) {
            $this->deleteOldLinks(array_keys($linksData['product_ids']));
            $mainData = [];
            foreach ($linksData['relation'] as $productData) {
                $mainData[] = [
                    'product_id' => $productData['parent_id'],
                    'linked_product_id' => $productData['child_id'],
                    'link_type_id' => $this->getLinkTypeId()
                ];
                $mongoContent[$productData['child_id']]['groupId'] = $productData['parent_id'];

            }
            $this->connection->insertOnDuplicate($mainTable, $mainData);
            $this->connection->insertOnDuplicate($relationTable, $linksData['relation']);
        }

        $attributes = $this->getAttributes();
        // save positions and default quantity
        if ($linksData['attr_product_ids']) {
            $savedData = $this->connection->fetchPairs(
                $this->connection->select()->from(
                    $mainTable,
                    [new \Zend_Db_Expr('CONCAT_WS(" ", product_id, linked_product_id)'), 'link_id']
                )->where(
                    'product_id IN (?) AND link_type_id = ' . $this->connection->quote($this->getLinkTypeId()),
                    array_keys($linksData['attr_product_ids'])
                )
            );
            foreach ($savedData as $pseudoKey => $linkId) {
                if (isset($linksData['position'][$pseudoKey])) {
                    $linksData['position'][$pseudoKey]['link_id'] = $linkId;
                }
                if (isset($linksData['qty'][$pseudoKey])) {
                    $linksData['qty'][$pseudoKey]['link_id'] = $linkId;
                }
            }
            if (!empty($linksData['position'])) {
                $this->connection->insertOnDuplicate($attributes['position']['table'], $linksData['position']);
                foreach($linksData['position'] as $itemKey => $itemValue) {
                    if (isset($itemValue['child_id'])) {
                        $mongoContent[$itemValue['child_id']]['groupSequence'] = $itemValue['value'];
                    }
                }
            }
            if (!empty($linksData['qty'])) {
                $this->connection->insertOnDuplicate($attributes['qty']['table'], $linksData['qty']);
            }
        }
        // save relations to MongoDB
        if($mongoContent) {
            $operations = [];
            foreach ($mongoContent as $id => $links) {
                $operations[] = ['updateOne' =>
                    [[$entityIdField => $id], ['$set' => $links], ['upsert' => true]]
                ];
            }
            $this->mongoAdapter->bulkWrite($collectionId, $operations);
        }
    }
}
