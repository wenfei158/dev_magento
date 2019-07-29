<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCatalog\Model\ResourceModel\Product;

use Magento\Framework\App\ObjectManager;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;

/**
 * Catalog Product Mass processing resource model
 *
 */
class Action extends \Magento\Catalog\Model\ResourceModel\Product\Action
{
    /**
     * Insert or Update attribute data
     *
     * @param \Magento\Catalog\Model\AbstractModel $object
     * @param AbstractAttribute $attribute
     * @param mixed $value
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _saveAttributeValue($object, $attribute, $value)
    {
        $connection = $this->getConnection();
        $storeId = (int) $this->_storeManager->getStore($object->getStoreId())->getId();
        $table = $attribute->getBackend()->getTable();

        $entityId = $this->resolveEntityId($object->getId(), $table);

        /**
         * If we work in single store mode all values should be saved just
         * for default store id
         * In this case we clear all not default values
         */
        if ($this->_storeManager->hasSingleStore() && $table != PatchData::BACKEND_TABLE_NAME) {
            $storeId = $this->getDefaultStoreId();
            $connection->delete(
                $table,
                [
                    'attribute_id = ?' => $attribute->getAttributeId(),
                    $this->getLinkField() . ' = ?' => $entityId,
                    'store_id <> ?' => $storeId
                ]
            );
        }

        $data = new \Magento\Framework\DataObject(
            [
                'attribute_id' => $attribute->getAttributeId(),
                'attribute_code' => $attribute->getAttributeCode(),
                'store_id' => $storeId,
                $this->getLinkField() => $entityId,
                'value' => $this->_prepareValueForSave($value, $attribute),
            ]
        );
        if ($table != PatchData::BACKEND_TABLE_NAME) {
            $bind = $this->_prepareDataForTable($data, $table);
        }
        if ($attribute->isScopeStore()) {
            /**
             * Update attribute value for store
             */
            $this->_attributeValuesToSave[$table][] = $bind;
        } elseif ($attribute->isScopeWebsite() && $storeId != $this->getDefaultStoreId()) {
            /**
             * Update attribute value for website
             */
            $storeIds = $this->_storeManager->getStore($storeId)->getWebsite()->getStoreIds(true);
            foreach ($storeIds as $storeId) {
                $bind['store_id'] = (int) $storeId;
                $this->_attributeValuesToSave[$table][] = $bind;
            }
        } else {
            /**
             * Update global attribute value
             */
            $bind['store_id'] = $this->getDefaultStoreId();
            $this->_attributeValuesToSave[$table][] = $bind;
        }

        return $this;
    }

    /**
     * Save and delete collected attribute values
     *
     * @return $this
     */
    protected function _processAttributeValues()
    {
        $connection = $this->getConnection();
        $mongoAdapter = ObjectManager::getInstance()->get(Adapter::class);
        foreach ($this->_attributeValuesToSave as $table => $data) {
            if($table == PatchData::BACKEND_TABLE_NAME) {
                $storeId = isset($data['$storeId']) ? $data['storeId'] : 0;
                $mongoAdapter->updateOne($storeId, );
            } else {
                $connection->insertOnDuplicate($table, $data, ['value']);
            }
        }

        foreach ($this->_attributeValuesToDelete as $table => $valueIds) {
            if($table == PatchData::BACKEND_TABLE_NAME) {
                $mongoAdapter->
            } else {
                $connection->delete($table, ['value_id IN (?)' => $valueIds]);
            }
        }

        // reset data arrays
        $this->_attributeValuesToSave = [];
        $this->_attributeValuesToDelete = [];

        return $this;
    }
}
