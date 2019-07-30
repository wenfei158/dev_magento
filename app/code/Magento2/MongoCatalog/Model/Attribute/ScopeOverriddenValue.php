<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\Attribute;

use Magento\Framework\EntityManager\MetadataPool;
use Magento\Eav\Api\AttributeRepositoryInterface as AttributeRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Store\Model\Store;
use Magento\Framework\App\ResourceConnection;
use Magento2\MongoCore\Model\Adapter\Query\Builder\Query;
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;

/**
 * Class ScopeOverriddenValue
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ScopeOverriddenValue extends \Magento\Catalog\Model\Attribute\ScopeOverriddenValue
{
    /**
     * @var AttributeRepository
     */
    private $attributeRepository;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var array
     */
    private $attributesValues;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var Query
     */
    protected $_mongoQuery;

    /**
     * ScopeOverriddenValue constructor.
     * @param AttributeRepository $attributeRepository
     * @param MetadataPool $metadataPool
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param ResourceConnection $resourceConnection
     * @param Query $query
     */
    public function __construct(
        AttributeRepository $attributeRepository,
        MetadataPool $metadataPool,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        ResourceConnection $resourceConnection,
        Query $query
    ) {
        $this->_mongoQuery = $query;
        $this->attributeRepository = $attributeRepository;
        $this->metadataPool = $metadataPool;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->resourceConnection = $resourceConnection;
        parent::__construct(
            $attributeRepository,
            $metadataPool,
            $searchCriteriaBuilder,
            $filterBuilder,
            $resourceConnection
        );
    }

    /**
     * Whether attribute value is overridden in specific store
     *
     * @param string $entityType
     * @param \Magento\Catalog\Model\AbstractModel $entity
     * @param string $attributeCode
     * @param int|string $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function containsValue($entityType, $entity, $attributeCode, $storeId)
    {
        if ((int)$storeId === Store::DEFAULT_STORE_ID) {
            return false;
        }
        if ($this->attributesValues === null) {
            $this->initAttributeValues($entityType, $entity, (int)$storeId);
        }

        return isset($this->attributesValues[$storeId])
            && array_key_exists($attributeCode, $this->attributesValues[$storeId]);
    }

    /**
     * Get attribute default values
     *
     * @param string $entityType
     * @param \Magento\Catalog\Model\AbstractModel $entity
     * @return array
     *
     * @deprecated 101.0.0
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDefaultValues($entityType, $entity)
    {
        if ($this->attributesValues === null) {
            $this->initAttributeValues($entityType, $entity, (int)$entity->getStoreId());
        }

        return isset($this->attributesValues[Store::DEFAULT_STORE_ID])
            ? $this->attributesValues[Store::DEFAULT_STORE_ID]
            : [];
    }

    /**
     * @param string $entityType
     * @param \Magento\Catalog\Model\AbstractModel $entity
     * @param int $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    private function initAttributeValues($entityType, $entity, $storeId)
    {
        $metadata = $this->metadataPool->getMetadata($entityType);
        /** @var \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute */
        $attributeTables = [];
        $MongoAttributes = [];
        if ($metadata->getEavEntityType()) {
            foreach ($this->getAttributes($entityType) as $attribute) {
                if (!$attribute->isStatic()) {
                    $table = $attribute->getBackend()->getTable();
                    if($table == PatchData::BACKEND_TABLE_NAME) {
                        $MongoAttributes[] = $attribute->getAttributeCode();
                    } else {
                        $attributeTables[$table][] = $attribute->getAttributeId();
                    }
                }
            }
            $storeIds = [Store::DEFAULT_STORE_ID];
            if ($storeId !== Store::DEFAULT_STORE_ID) {
                $storeIds[] = $storeId;
            }
            $entityIdField = $metadata->getLinkField();
            $entityId = $entity->getData($metadata->getLinkField());
            $selects = [];
            foreach ($attributeTables as $attributeTable => $attributeCodes) {
                $select = $metadata->getEntityConnection()->select()
                    ->from(['t' => $attributeTable], ['value' => 't.value', 'store_id' => 't.store_id'])
                    ->join(
                        ['a' => $this->resourceConnection->getTableName('eav_attribute')],
                        'a.attribute_id = t.attribute_id',
                        ['attribute_code' => 'a.attribute_code']
                    )
                    ->where( $entityIdField . ' = ?', $entityId)
                    ->where('t.attribute_id IN (?)', $attributeCodes)
                    ->where('t.store_id IN (?)', $storeIds);
                $selects[] = $select;
            }

            $unionSelect = new \Magento\Framework\DB\Sql\UnionExpression(
                $selects,
                \Magento\Framework\DB\Select::SQL_UNION_ALL
            );
            $attributes = $metadata->getEntityConnection()->fetchAll((string)$unionSelect);
            foreach ($attributes as $attribute) {
                $this->attributesValues[$attribute['store_id']][$attribute['attribute_code']] = $attribute['value'];
            }
            //TODO: Multiple storeIds Process
            if(count($MongoAttributes)) {
                $result = $this->_mongoQuery->getById(Store::DEFAULT_STORE_ID, $entityId, $MongoAttributes);
                foreach ($MongoAttributes as $attributeCode) {
                    if (isset($result[$attributeCode])) {
                        $this->attributesValues[Store::DEFAULT_STORE_ID][$attributeCode] = $result[$attributeCode];
                    }
                }
            }
        }
    }

    /**
     * @param string $entityType
     * @return \Magento\Eav\Api\Data\AttributeInterface[]
     * @throws \Exception
     */
    private function getAttributes($entityType)
    {
        $metadata = $this->metadataPool->getMetadata($entityType);
        $searchResult = $this->attributeRepository->getList(
            $metadata->getEavEntityType(),
            $this->searchCriteriaBuilder->addFilters(
                [
                    $this->filterBuilder
                        ->setField('is_global')
                        ->setConditionType('in')
                        ->setValue([ScopedAttributeInterface::SCOPE_STORE, ScopedAttributeInterface::SCOPE_WEBSITE])
                        ->create()
                ]
            )->create()
        );
        return $searchResult->getItems();
    }
}
