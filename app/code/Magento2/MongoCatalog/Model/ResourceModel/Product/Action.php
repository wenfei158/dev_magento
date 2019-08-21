<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCatalog\Model\ResourceModel\Product;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Eav\Model\Entity\Attribute\UniqueValidationInterface;
use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;

/**
 * Catalog Product Mass processing resource model
 *
 */
class Action extends \Magento\Catalog\Model\ResourceModel\Product\Action
{
    /**
     * @var Adapter
     */
    protected $_mongoAdapter;

    /**
     * @var UniqueValidationInterface
     */
    private $uniqueValidator;

    /**
     * @param \Magento\Eav\Model\Entity\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Factory $modelFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category $catalogCategory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $setFactory
     * @param \Magento\Eav\Model\Entity\TypeFactory $typeFactory
     * @param \Magento\Catalog\Model\Product\Attribute\DefaultAttributes $defaultAttributes
     * @param Adapter $adapter
     * @param array $data
     * @param TableMaintainer|null $tableMaintainer
     * @param UniqueValidationInterface|null $uniqueValidator
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Eav\Model\Entity\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Factory $modelFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Category $catalogCategory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $setFactory,
        \Magento\Eav\Model\Entity\TypeFactory $typeFactory,
        \Magento\Catalog\Model\Product\Attribute\DefaultAttributes $defaultAttributes,
        Adapter $adapter,
        $data = [],
        TableMaintainer $tableMaintainer = null,
        UniqueValidationInterface $uniqueValidator = null
    ) {
        $this->_mongoAdapter = $adapter;
        $this->uniqueValidator = $uniqueValidator ?:
            ObjectManager::getInstance()->get(UniqueValidationInterface::class);
        parent::__construct(
            $context,
            $storeManager,
            $modelFactory,
            $categoryCollectionFactory,
            $catalogCategory,
            $eventManager,
            $setFactory,
            $typeFactory,
            $defaultAttributes,
            $data,
            $tableMaintainer,
            $this->uniqueValidator
        );
    }

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
        $entityIdField = $this->getLinkField();
        $entityId = $this->resolveEntityId($object->getId(), $table);

        /**
         * If we work in single store mode all values should be saved just
         * for default store id
         * In this case we clear all not default values
         */
        if ($this->_storeManager->hasSingleStore()) {
            $storeId = $this->getDefaultStoreId();
            if($table == PatchData::BACKEND_TABLE_NAME) {
                $attributeCode = $attribute->getAttributeCode();
                $filter = [$entityIdField => ['$eq' => (integer)$entityId]];
                $update = ['$unset' => [$attributeCode => ""]];
                $this->_mongoAdapter->updateOne($storeId, $filter, $update);
            } else {
                $connection->delete(
                    $table,
                    [
                        'attribute_id = ?' => $attribute->getAttributeId(),
                        $entityIdField . ' = ?' => $entityId,
                        'store_id <> ?' => $storeId
                    ]
                );
            }
        }

        $data = new \Magento\Framework\DataObject(
            [
                'attribute_id' => $attribute->getAttributeId(),
                'attribute_code' => $attribute->getAttributeCode(),
                'store_id' => $storeId,
                $entityIdField => $entityId,
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
        foreach ($this->_attributeValuesToSave as $table => $data) {
            if($table == PatchData::BACKEND_TABLE_NAME) {
                $storeId = isset($data['$storeId']) ? $data['storeId'] : 0;
                $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
                $entityId = $data[$entityIdField];
                if(isset($data['attribute_code'])) {
                    $attributeCode = $data['attribute_code'];
                } else {
                    $attributeCode = $this->getAttribute($data['attribute_id'])->getAttributeCode();
                }
                $filter = [$entityIdField => ['$eq' => (integer)$entityId]];
                $update = ['$set' => [$attributeCode => $data['value']]];;
                $this->_mongoAdapter->updateOne($storeId, $filter, $update, ['upsert' => true]);
            } else {
                $connection->insertOnDuplicate($table, $data, ['value']);
            }
        }

        foreach ($this->_attributeValuesToDelete as $table => $valueIds) {
            if($table != PatchData::BACKEND_TABLE_NAME) {
                $values = [];
                foreach ($valueIds as $key => $value) {
                    $values = array_merge($values, $value);
                }
                $connection->delete($table, ['value_id IN (?)' => $values]);
            }
        }

        // reset data arrays
        $this->_attributeValuesToSave = [];
        $this->_attributeValuesToDelete = [];

        return $this;
    }
}
