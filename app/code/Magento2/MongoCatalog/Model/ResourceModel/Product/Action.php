<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCatalog\Model\ResourceModel\Product;

use Magento\Framework\DataObject;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Eav\Model\Entity\Attribute\UniqueValidationInterface;
use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento2\MongoCore\Model\Adapter\Query\Builder\Query;
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;

/**
 * Catalog Product Mass processing resource model
 *
 */
class Action extends \Magento\Catalog\Model\ResourceModel\Product\Action
{
    /**
     * @var PatchData
     */
    protected $_patchData;

    /**
     * @var Adapter
     */
    protected $_mongoAdapter;

    /**
     * @var Query
     */
    protected $_mongoQuery;

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
     * @param PatchData $patchData
     * @param Adapter $adapter
     * @param Query $query
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
        PatchData $patchData,
        Adapter $adapter,
        Query $query,
        $data = [],
        TableMaintainer $tableMaintainer = null,
        UniqueValidationInterface $uniqueValidator = null
    ) {
        $this->_patchData = $patchData;
        $this->_mongoAdapter = $adapter;
        $this->_mongoQuery = $query;
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
     * Save attribute
     *
     * @param DataObject $object
     * @param string $attributeCode
     * @return $this
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function saveAttribute(DataObject $object, $attributeCode)
    {
        $attribute = $this->getAttribute($attributeCode);
        $backend = $attribute->getBackend();
        $table = $backend->getTable();
        $entity = $attribute->getEntity();
        $connection = $this->getConnection();
        $row = $this->getAttributeRow($entity, $object, $attribute);

        $newValue = $object->getData($attributeCode);
        if ($attribute->isValueEmpty($newValue)) {
            $newValue = null;
        }
        if($table == PatchData::BACKEND_TABLE_NAME) {
            $entityIdField = $this->getLinkField();
            $storeId = $row['store_id'] ? : 0;
            $entityId = $object->getData($this->getLinkField());
            $filter = [$entityIdField => ['$eq' => (integer)$entityId]];
            if($newValue == null) {
                $update = ['$unset' => [$attributeCode => ""]];
                $this->_mongoAdapter->updateOne($storeId, $filter, $update);
            } else {
                $update = ['$set' => [$attributeCode => $newValue]];
                $this->_mongoAdapter->updateOne($storeId, $filter, $update, ['upsert' => true]);
            }
        } else {
            $whereArr = [];
            foreach ($row as $field => $value) {
                $whereArr[] = $connection->quoteInto($field . '=?', $value);
            }
            $where = implode(' AND ', $whereArr);

            $connection->beginTransaction();

            try {
                $select = $connection->select()->from($table, ['value_id', 'value'])->where($where);
                $origRow = $connection->fetchRow($select);
                $origValueId = $origRow['value_id'] ?? false;
                $origValue = $origRow['value'] ?? null;
                if ($origValueId === false && $newValue !== null) {
                    $this->_insertAttribute($object, $attribute, $newValue);
                } elseif ($origValueId !== false && $newValue !== null) {
                    $this->_updateAttribute($object, $attribute, $origValueId, $newValue);
                } elseif ($origValueId !== false && $newValue === null && $origValue !== null) {
                    $connection->delete($table, $where);
                }
                $this->_processAttributeValues();
                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollBack();
                throw $e;
            }
        }

        return $this;
    }

    /**
     * Check attribute unique value
     *
     * @param AbstractAttribute $attribute
     * @param DataObject $object
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkAttributeUniqueValue(AbstractAttribute $attribute, $object)
    {
        $attributeCode = $attribute->getAttributeCode();
        if($this->_patchData->isMongoAttribute($attributeCode)) {
            throw new \Exception(
                'Can not use Mongo Attribute(' . $attributeCode . ') in checkAttributeUniqueValue Function in product resource model.'
            );
        }
        $connection = $this->getConnection();
        $select = $connection->select();

        $entityIdField = $this->getEntityIdField();
        $attributeBackend = $attribute->getBackend();
        if ($attributeBackend->getType() === 'static') {
            $value = $object->getData($attribute->getAttributeCode());
            $bind = ['value' => trim($value)];

            $select->from(
                $this->getEntityTable(),
                $entityIdField
            )->where(
                $attribute->getAttributeCode() . ' = :value'
            );
        } else {
            $value = $object->getData($attribute->getAttributeCode());
            if ($attributeBackend->getType() == 'datetime') {
                $value = (new \DateTime($value))->format('Y-m-d H:i:s');
            }
            $bind = [
                'attribute_id' => $attribute->getId(),
                'value' => trim($value),
            ];

            $entityIdField = $object->getResource()->getLinkField();
            $select->from(
                $attributeBackend->getTable(),
                $entityIdField
            )->where(
                'attribute_id = :attribute_id'
            )->where(
                'value = :value'
            );
        }

        if ($this->getEntityTable() == \Magento\Eav\Model\Entity::DEFAULT_ENTITY_TABLE) {
            $bind['entity_type_id'] = $this->getTypeId();
            $select->where('entity_type_id = :entity_type_id');
        }

        $data = $connection->fetchCol($select, $bind);

        if ($object->getData($entityIdField)) {
            return $this->uniqueValidator->validate($attribute, $object, $this, $entityIdField, $data);
        }

        return !count($data);
    }

    /**
     * Load model attributes data
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _loadModelAttributes($object)
    {
        $entityId = $object->getId();
        if (!$entityId) {
            return $this;
        }

        \Magento\Framework\Profiler::start('load_model_attributes');

        $selects = [];
        foreach (array_keys($this->getAttributesByTable()) as $table) {
            if($table == PatchData::BACKEND_TABLE_NAME) {
                $attributeCodeList = [];
                foreach ($this->_attributesByTable[$table] as $attribute){
                    $attributeCodeList[] = $attribute->getAttributeCode();
                }
                if(!empty($attributeCodeList)) {
                    $storeId = $object->getStoreId() ? : $this->getDefaultStoreId();
                    $result = $this->_mongoQuery->getById($storeId, $entityId, $attributeCodeList);
                    foreach($attributeCodeList as $attributeCode) {
                        if (isset($result[$attributeCode])) {
                            $object->setData($attributeCode, $result[$attributeCode]);
                        }
                    }
                }
            } else {
                $attribute = current($this->_attributesByTable[$table]);
                $eavType = $attribute->getBackendType();
                $select = $this->_getLoadAttributesSelect($object, $table);
                $selects[$eavType][] = $select->columns('*');
            }
        }
        $selectGroups = $this->_resourceHelper->getLoadAttributesSelectGroups($selects);
        foreach ($selectGroups as $selects) {
            if (!empty($selects)) {
                if (is_array($selects)) {
                    $select = $this->_prepareLoadSelect($selects);
                } else {
                    $select = $selects;
                }
                $values = $this->getConnection()->fetchAll($select);
                foreach ($values as $valueRow) {
                    $this->_setAttributeValue($object, $valueRow);
                }
            }
        }

        \Magento\Framework\Profiler::stop('load_model_attributes');

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

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @since 101.0.0
     */
    protected function evaluateDelete($object, $id, $connection)
    {
        $where = [$this->getLinkField() . '=?' => $id];
        $this->objectRelationProcessor->delete(
            $this->transactionManager,
            $connection,
            $this->getEntityTable(),
            $this->getConnection()->quoteInto(
                $this->getLinkField() . '=?',
                $id
            ),
            [$this->getLinkField() => $id]
        );

        $storeIds = $object->getStoreIds();
        $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
        $filter = [$entityIdField => ['$eq' => (integer)$id]];
        foreach($storeIds as $storeId) {
            $this->_mongoAdapter->deleteOne($storeId, $filter);
        }
        $this->loadAllAttributes($object);
        foreach ($this->getAttributesByTable() as $table => $attributes) {
            if ($table != PatchData::BACKEND_TABLE_NAME) {
                $this->getConnection()->delete(
                    $table,
                    $where
                );
            }
        }
    }

    /**
     * Update attribute value for specific store
     *
     * @param \Magento\Catalog\Model\AbstractModel $object
     * @param object $attribute
     * @param mixed $value
     * @param int $storeId
     * @return $this
     */
    protected function _updateAttributeForStore($object, $attribute, $value, $storeId)
    {
        $connection = $this->getConnection();
        $table = $attribute->getBackend()->getTable();
        $entityIdField = $this->getLinkField();
        $entityId = $object->getId();
        if($table == PatchData::BACKEND_TABLE_NAME) {
            $attributeCode = $attribute->getAttributeCode();
            $filter = [$entityIdField => ['$eq' => (integer)$entityId]];
            $update = ['$set' => [$attributeCode => $value]];
            $this->_mongoAdapter->updateOne($storeId, $filter, $update, ['upsert' => true]);
        } else {
            $select = $connection->select()
                ->from($table, 'value_id')
                ->where("$entityIdField = :entity_field_id")
                ->where('store_id = :store_id')
                ->where('attribute_id = :attribute_id');
            $bind = [
                'entity_field_id' => $entityId,
                'store_id' => $storeId,
                'attribute_id' => $attribute->getId(),
            ];
            $valueId = $connection->fetchOne($select, $bind);
            /**
             * When value for store exist
             */
            if ($valueId) {
                $bind = ['value' => $this->_prepareValueForSave($value, $attribute)];
                $where = ['value_id = ?' => (int) $valueId];

                $connection->update($table, $bind, $where);
            } else {
                $bind = [
                    $entityIdField => (int) $entityId,
                    'attribute_id' => (int) $attribute->getId(),
                    'value' => $this->_prepareValueForSave($value, $attribute),
                    'store_id' => (int) $storeId,
                ];

                $connection->insert($table, $bind);
            }
        }

        return $this;
    }

    /**
     * Insert entity attribute value
     *
     * @param \Magento\Framework\DataObject $object
     * @param AbstractAttribute $attribute
     * @param mixed $value
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _insertAttribute($object, $attribute, $value)
    {
        /**
         * save required attributes in global scope every time if store id different from default
         */
        $storeId = (int) $this->_storeManager->getStore($object->getStoreId())->getId();
        if ($this->getDefaultStoreId() != $storeId) {
            if ($attribute->getIsRequired() || $attribute->getIsRequiredInAdminStore()) {
                $table = $attribute->getBackend()->getTable();
                if($table == PatchData::BACKEND_TABLE_NAME) {
                    $entityIdField = $this->getLinkField();
                    $entityId = $object->getData($this->getLinkField());
                    $filter = [$entityIdField => ['$eq' => (integer)$entityId]];
                    $attributeCode = $attribute->getAttributeCode();
                    $update = ['$set' => [$attributeCode => $value]];
                    $this->_mongoAdapter->updateOne($storeId, $filter, $update, ['upsert' => true]);
                } else {
                    $select = $this->getConnection()->select()
                        ->from($table)
                        ->where('attribute_id = ?', $attribute->getAttributeId())
                        ->where('store_id = ?', $this->getDefaultStoreId())
                        ->where($this->getLinkField() . ' = ?', $object->getData($this->getLinkField()));
                    $row = $this->getConnection()->fetchOne($select);

                    if (!$row) {
                        $data = new \Magento\Framework\DataObject(
                            [
                                'attribute_id' => $attribute->getAttributeId(),
                                'store_id' => $this->getDefaultStoreId(),
                                $this->getLinkField() => $object->getData($this->getLinkField()),
                                'value' => $this->_prepareValueForSave($value, $attribute),
                            ]
                        );
                        $bind = $this->_prepareDataForTable($data, $table);
                        $this->getConnection()->insertOnDuplicate($table, $bind, ['value']);
                    }
                }
            }
        }

        return $this->_saveAttributeValue($object, $attribute, $value);
    }

    /**
     * Delete entity attribute values
     *
     * @param \Magento\Framework\DataObject $object
     * @param string $table
     * @param array $info
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _deleteAttributes($object, $table, $info)
    {
        $connection = $this->getConnection();
        $entityIdField = $this->getLinkField();
        $id = $object->getId();
        $globalValues = [];
        $websiteAttributes = [];
        $storeAttributes = [];

        /**
         * Separate attributes by scope
         */
        foreach ($info as $itemData) {
            $attribute = $this->getAttribute($itemData['attribute_id']);
            if($table == PatchData::BACKEND_TABLE_NAME) {
                $attributeCode = $attribute->getAttributeCode();
                $filter = [$entityIdField => ['$eq' => (integer)$id]];
                $update = ['$unset' => [$attributeCode => ""]];
                if ($attribute->isScopeStore()) {
                    $storeId = $object->getStoreId();
                    $this->_mongoAdapter->updateOne($storeId, $filter, $update);
                } elseif ($attribute->isScopeWebsite()) {
                    $storeIds = $object->getWebsiteStoreIds();
                    if (!empty($storeIds)) {
                        foreach ($storeIds as $storeId) {
                            $this->_mongoAdapter->updateOne($storeId, $filter, $update);
                        }
                    }
                } elseif ($itemData['value_id'] !== null) {
                    $storeId = \Magento\Store\Model\Store::DEFAULT_STORE_ID;
                    $this->_mongoAdapter->updateOne($storeId, $filter, $update);
                }
            } else {
                if ($attribute->isScopeStore()) {
                    $storeAttributes[] = (int) $itemData['attribute_id'];
                } elseif ($attribute->isScopeWebsite()) {
                    $websiteAttributes[] = (int) $itemData['attribute_id'];
                } elseif ($itemData['value_id'] !== null) {
                    $globalValues[] = (int) $itemData['value_id'];
                }
            }
        }

        /**
         * Delete global scope attributes
         */
        if (!empty($globalValues)) {
            $connection->delete($table, ['value_id IN (?)' => $globalValues]);
        }

        $condition = [
            $entityIdField . ' = ?' => $id,
        ];

        /**
         * Delete website scope attributes
         */
        if (!empty($websiteAttributes)) {
            $storeIds = $object->getWebsiteStoreIds();
            if (!empty($storeIds)) {
                $delCondition = $condition;
                $delCondition['attribute_id IN(?)'] = $websiteAttributes;
                $delCondition['store_id IN(?)'] = $storeIds;

                $connection->delete($table, $delCondition);
            }
        }

        /**
         * Delete store scope attributes
         */
        if (!empty($storeAttributes)) {
            $delCondition = $condition;
            $delCondition['attribute_id IN(?)'] = $storeAttributes;
            $delCondition['store_id = ?'] = (int) $object->getStoreId();

            $connection->delete($table, $delCondition);
        }

        return $this;
    }

    /**
     * Retrieve attribute's raw value from DB.
     *
     * @param int $entityId
     * @param int|string|array $attribute attribute's ids or codes
     * @param int|\Magento\Store\Model\Store $store
     * @return bool|string|array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributeRawValue($entityId, $attribute, $store)
    {
        if (!$entityId || empty($attribute)) {
            return false;
        }
        if (!is_array($attribute)) {
            $attribute = [$attribute];
        }

        $attributesData = [];
        $staticAttributes = [];
        $typedAttributes = [];
        $staticTable = null;
        $connection = $this->getConnection();

        foreach ($attribute as $item) {
            /* @var $attribute \Magento\Catalog\Model\Entity\Attribute */
            $item = $this->getAttribute($item);
            if (!$item) {
                continue;
            }
            $attributeCode = $item->getAttributeCode();
            $attrTable = $item->getBackend()->getTable();
            $isStatic = $item->getBackend()->isStatic();

            if ($isStatic) {
                $staticAttributes[] = $attributeCode;
                $staticTable = $attrTable;
            } else {
                /**
                 * That structure needed to avoid farther sql joins for getting attribute's code by id
                 */
                $typedAttributes[$attrTable][$item->getId()] = $attributeCode;
            }
        }

        /**
         * Collecting static attributes
         */
        if ($staticAttributes) {
            $select = $connection->select()->from(
                $staticTable,
                $staticAttributes
            )->join(
                ['e' => $this->getTable($this->getEntityTable())],
                'e.' . $this->getLinkField() . ' = ' . $staticTable . '.' . $this->getLinkField()
            )->where(
                'e.entity_id = :entity_id'
            );
            $attributesData = $connection->fetchRow($select, ['entity_id' => $entityId]);
        }

        /**
         * Collecting typed attributes, performing separate SQL query for each attribute type table
         */
        if ($store instanceof \Magento\Store\Model\Store) {
            $store = $store->getId();
        }

        $store = (int) $store;
        if ($typedAttributes) {
            foreach ($typedAttributes as $table => $_attributes) {
                if($table == PatchData::BACKEND_TABLE_NAME) {
                    $attributeCodeList = array_values($_attributes);
                    $result = $this->_mongoQuery->getById($store, $entityId, $attributeCodeList);
                    foreach ($attributeCodeList as $attributeCode) {
                        if (isset($result[$attributeCode])) {
                            $attributesData[$attributeCode] = $result[$attributeCode];
                        }
                    }
                } else {
                    $select = $connection->select()
                        ->from(['default_value' => $table], ['attribute_id'])
                        ->join(
                            ['e' => $this->getTable($this->getEntityTable())],
                            'e.' . $this->getLinkField() . ' = ' . 'default_value.' . $this->getLinkField(),
                            ''
                        )->where('default_value.attribute_id IN (?)', array_keys($_attributes))
                        ->where("e.entity_id = :entity_id")
                        ->where('default_value.store_id = ?', 0);

                    $bind = ['entity_id' => $entityId];

                    if ($store != $this->getDefaultStoreId()) {
                        $valueExpr = $connection->getCheckSql(
                            'store_value.value IS NULL',
                            'default_value.value',
                            'store_value.value'
                        );
                        $joinCondition = [
                            $connection->quoteInto('store_value.attribute_id IN (?)', array_keys($_attributes)),
                            "store_value.{$this->getLinkField()} = e.{$this->getLinkField()}",
                            'store_value.store_id = :store_id',
                        ];

                        $select->joinLeft(
                            ['store_value' => $table],
                            implode(' AND ', $joinCondition),
                            ['attr_value' => $valueExpr]
                        );

                        $bind['store_id'] = $store;
                    } else {
                        $select->columns(['attr_value' => 'value'], 'default_value');
                    }

                    $result = $connection->fetchPairs($select, $bind);
                    foreach ($result as $attrId => $value) {
                        $attrCode = $typedAttributes[$table][$attrId];
                        $attributesData[$attrCode] = $value;
                    }
                }
            }
        }

        if (is_array($attributesData) && sizeof($attributesData) == 1) {
            $attributesData = array_shift($attributesData);
        }

        return $attributesData === false ? false : $attributesData;
    }

}
