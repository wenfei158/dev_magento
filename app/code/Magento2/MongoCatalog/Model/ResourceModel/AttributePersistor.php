<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel;

use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Model\ResourceModel\Attribute\ConditionBuilder;
use Magento\Framework\Model\Entity\ScopeInterface;
use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;

/**
 * Class AttributePersistor
 */
class AttributePersistor extends \Magento\Catalog\Model\ResourceModel\AttributePersistor
{
    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var FormatInterface
     */
    private $localeFormat;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var Adapter
     */
    private $mongoAdapter;

    /**
     * @var array
     */
    private $insert = [];

    /**
     * @var array
     */
    private $update = [];

    /**
     * @var array
     */
    private $delete = [];

    /**
     * @param FormatInterface $localeFormat
     * @param AttributeRepositoryInterface $attributeRepository
     * @param MetadataPool $metadataPool
     * @param ConditionBuilder $conditionBuilder
     * @param Adapter $adapter
     */
    public function __construct(
        FormatInterface $localeFormat,
        AttributeRepositoryInterface $attributeRepository,
        MetadataPool $metadataPool,
        ConditionBuilder $conditionBuilder = null,
        Adapter $adapter
    ) {
        $this->mongoAdapter = $adapter;
        $this->localeFormat = $localeFormat;
        $this->attributeRepository = $attributeRepository;
        $this->metadataPool = $metadataPool;
        parent::__construct($localeFormat, $attributeRepository, $metadataPool, $conditionBuilder);
    }

    /**
     * @param string $entityType
     * @param int $link
     * @param string $attributeCode
     * @return void
     */
    public function registerDelete($entityType, $link, $attributeCode)
    {
        $this->delete[$entityType][$link][$attributeCode] = null;
    }

    /**
     * @param string $entityType
     * @param int $link
     * @param string $attributeCode
     * @param mixed $value
     * @return void
     */
    public function registerUpdate($entityType, $link, $attributeCode, $value)
    {
        $this->update[$entityType][$link][$attributeCode] = $value;
    }

    /**
     * @param string $entityType
     * @param int $link
     * @param string $attributeCode
     * @param mixed $value
     * @return void
     */
    public function registerInsert($entityType, $link, $attributeCode, $value)
    {
        $this->insert[$entityType][$link][$attributeCode] = $value;
    }

    /**
     * @param string $entityType
     * @param \Magento\Framework\Model\Entity\ScopeInterface[] $context
     * @return void
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processDeletes($entityType, $context)
    {
        if (!isset($this->delete[$entityType]) || !is_array($this->delete[$entityType])) {
            return;
        }
        $metadata = $this->metadataPool->getMetadata($entityType);
        $mongoAttributes = [];
        foreach ($this->delete[$entityType] as $link => $data) {
            $attributeCodes = array_keys($data);
            foreach ($attributeCodes as $attributeCode) {
                /** @var AbstractAttribute $attribute */
                $attribute = $this->attributeRepository->get($metadata->getEavEntityType(), $attributeCode);
                $table = $attribute->getBackend()->getTable();
                if ($table == PatchData::BACKEND_TABLE_NAME) {
                    foreach ($context as $scope) {
                        $storeId = $this->getScopeValue($scope, $attribute);
                        $mongoAttributes[$storeId][$link][$attributeCode] = "";
                    }
                } else {
                    $conditions = $this->buildDeleteConditions($attribute, $metadata, $context, $link);
                    foreach ($conditions as $condition) {
                        $metadata->getEntityConnection()->delete(
                            $attribute->getBackend()->getTable(),
                            $condition
                        );
                    }
                }
            }
        }
        $this->processMongoAttributes($mongoAttributes, "delete");
    }

    /**
     * @param string $entityType
     * @param \Magento\Framework\Model\Entity\ScopeInterface[] $context
     * @return void
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processInserts($entityType, $context)
    {
        if (!isset($this->insert[$entityType]) || !is_array($this->insert[$entityType])) {
            return;
        }
        $metadata = $this->metadataPool->getMetadata($entityType);
        $insertData = $this->prepareInsertDataForMultipleSave($entityType, $context);

        foreach ($insertData as $table => $tableData) {
            foreach ($tableData as $data) {
                $metadata->getEntityConnection()->insertArray(
                    $table,
                    $data['columns'],
                    $data['data'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_IGNORE
                );
            }
        }
    }

    /**
     * Prepare data for insert multiple rows
     *
     * @param string $entityType
     * @param \Magento\Framework\Model\Entity\ScopeInterface[] $context
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function prepareInsertDataForMultipleSave($entityType, $context)
    {
        $metadata = $this->metadataPool->getMetadata($entityType);
        $insertData = [];
        $mongoAttributes = [];
        foreach ($this->insert[$entityType] as $link => $data) {
            foreach ($data as $attributeCode => $attributeValue) {
                /** @var AbstractAttribute $attribute */
                $attribute = $this->attributeRepository->get(
                    $metadata->getEavEntityType(),
                    $attributeCode
                );
                $attributeTable = $attribute->getBackend()->getTable();
                if ($attributeTable == PatchData::BACKEND_TABLE_NAME) {
                    foreach ($context as $scope) {
                        $storeId = $this->getScopeValue($scope, $attribute);
                        $mongoAttributes[$storeId][$link][$attributeCode] = $attributeValue;
                    }
                } else {
                    $conditions = $this->buildInsertConditions($attribute, $metadata, $context, $link);
                    $value = $this->prepareValue($entityType, $attributeValue, $attribute);
                    foreach ($conditions as $condition) {
                        $condition['value'] = $value;
                        $columns = array_keys($condition);
                        $columnsHash = implode('', $columns);
                        $insertData[$attributeTable][$columnsHash]['columns'] = $columns;
                        $insertData[$attributeTable][$columnsHash]['data'][] = array_values($condition);
                    }
                }
            }
        }
        $this->processMongoAttributes($mongoAttributes);
        return $insertData;
    }

    /**
     * @param string $entityType
     * @param \Magento\Framework\Model\Entity\ScopeInterface[] $context
     * @return void
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processUpdates($entityType, $context)
    {
        if (!isset($this->update[$entityType]) || !is_array($this->update[$entityType])) {
            return;
        }
        $metadata = $this->metadataPool->getMetadata($entityType);
        $mongoAttributes = [];
        foreach ($this->update[$entityType] as $link => $data) {
            foreach ($data as $attributeCode => $attributeValue) {
                /** @var AbstractAttribute $attribute */
                $attribute = $this->attributeRepository->get(
                    $metadata->getEavEntityType(),
                    $attributeCode
                );
                $table = $attribute->getBackend()->getTable();
                if ($table == PatchData::BACKEND_TABLE_NAME) {
                    foreach ($context as $scope) {
                        $storeId = $this->getScopeValue($scope, $attribute);
                        $mongoAttributes[$storeId][$link][$attributeCode] = $attributeValue;
                    }
                } else {
                    $conditions = $this->buildUpdateConditions($attribute, $metadata, $context, $link);
                    foreach ($conditions as $condition) {
                        $metadata->getEntityConnection()->update(
                            $table,
                            [
                                'value' => $this->prepareValue($entityType, $attributeValue, $attribute)
                            ],
                            $condition
                        );
                    }
                }
            }
        }
        $this->processMongoAttributes($mongoAttributes);
    }

    /**
     * Flush attributes to storage
     *
     * @param string $entityType
     * @param ScopeInterface[] $context
     * @return void
     */
    public function flush($entityType, $context)
    {
        $this->processDeletes($entityType, $context);
        $this->processInserts($entityType, $context);
        $this->processUpdates($entityType, $context);
        unset($this->delete, $this->insert, $this->update);
    }

    /**
     * @param string $entityType
     * @param string $value
     * @param AbstractAttribute $attribute
     * @return mixed
     * @throws \Exception
     */
    protected function prepareValue($entityType, $value, AbstractAttribute $attribute)
    {
        $metadata = $this->metadataPool->getMetadata($entityType);
        $type = $attribute->getBackendType();
        if (($type == 'int' || $type == 'decimal' || $type == 'datetime') && $value === '') {
            $value = null;
        } elseif ($type == 'decimal') {
            $value = $this->localeFormat->getNumber($value);
        } elseif ($type == 'varchar' && is_array($value)) {
            $value = implode(',', $value);
        }
        $table = $attribute->getBackendTable();
        if($table == PatchData::BACKEND_TABLE_NAME) {
            return $value;
        }
        $describe = $metadata->getEntityConnection()->describeTable($table);
        return $metadata->getEntityConnection()->prepareColumnValue($describe['value'], $value);
    }

    /**
     * Process mongo attributes
     *
     * @param array $mongoAttributes
     * @param string $operation
     * @return void
     */
    private function processMongoAttributes($mongoAttributes, $operation = "update")
    {
        if ($operation == "delete") {
            $modifier = '$unset';
        } elseif ($operation == "insert") {
            $modifier = '$set';
        } else {
            $modifier = '$set';
        }
        if (count($mongoAttributes)) {
            $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
            foreach ($mongoAttributes as $collectionId => $documents) {
                $operations = [];
                foreach ($documents as $id => $attributes) {
                    if($operation == "delete") {
                        $operations[] = ['updateOne' =>
                            [[$entityIdField => ['$eq' => (integer)$id]], [$modifier => $attributes]]
                        ];
                    } else {
                        $operations[] = ['updateOne' =>
                            [[$entityIdField => ['$eq' => (integer)$id]], [$modifier => $attributes], ['upsert' => true]]
                        ];
                    }
                }
                $this->mongoAdapter->bulkWrite($collectionId, $operations);
            }
        }
    }
}
