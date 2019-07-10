<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel\Product;

use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitationFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Framework\Model\ResourceModel\ResourceModelPoolInterface;
use Magento2\MongoCore\Model\Adapter\Query\Builder\Query;

/**
 * Product collection
 *
 * @api
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @since 100.0.2
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * @var Query
     */
    protected $_mongoQuery;

    /**
     * Collection constructor
     *
     * @param \Magento\Framework\Data\Collection\EntityFactory $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Eav\Model\EntityFactory $eavEntityFactory
     * @param \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Validator\UniversalFactory $universalFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory
     * @param \Magento\Catalog\Model\ResourceModel\Url $catalogUrl
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param GroupManagementInterface $groupManagement
     * @param Query $mongoQuery
     * @param \Magento\Framework\DB\Adapter\AdapterInterface|null $connection
     * @param ProductLimitationFactory|null $productLimitationFactory
     * @param MetadataPool|null $metadataPool
     * @param TableMaintainer|null $tableMaintainer
     * @param PriceTableResolver|null $priceTableResolver
     * @param DimensionFactory|null $dimensionFactory
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Validator\UniversalFactory $universalFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \Magento\Catalog\Model\ResourceModel\Url $catalogUrl,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        GroupManagementInterface $groupManagement,
        Query $mongoQuery,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        ProductLimitationFactory $productLimitationFactory = null,
        MetadataPool $metadataPool = null,
        TableMaintainer $tableMaintainer = null,
        PriceTableResolver $priceTableResolver = null,
        DimensionFactory $dimensionFactory = null
    ) {
        $this->_mongoQuery = $mongoQuery;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $eavConfig,
            $resource,
            $eavEntityFactory,
            $resourceHelper,
            $universalFactory,
            $storeManager,
            $moduleManager,
            $catalogProductFlatState,
            $scopeConfig,
            $productOptionFactory,
            $catalogUrl,
            $localeDate,
            $customerSession,
            $dateTime,
            $groupManagement,
            $connection,
            $productLimitationFactory,
            $metadataPool,
            $tableMaintainer,
            $priceTableResolver,
            $dimensionFactory
        );
    }


    /**
     * Load attributes into loaded entities
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function _loadAttributes($printQuery = false, $logQuery = false)
    {
        if ($this->isEnabledFlat()) {
            return $this;
        }
        if (empty($this->_items) || empty($this->_itemsById) || empty($this->_selectAttributes)) {
            return $this;
        }

        $entity = $this->getEntity();

        $tableAttributes = [];
        $attributeTypes = [];
        $mongoAttributes = [];
        foreach ($this->_selectAttributes as $attributeCode => $attributeId) {
            if (!$attributeId) {
                continue;
            }
            $attribute = $this->_eavConfig->getAttribute($entity->getType(), $attributeCode);
            if ($attribute && !$attribute->isStatic()) {
                if($attribute->getBackendTable() == 'mongo') {
                    $mongoAttributes[] = $attributeId;
                } else {
                    $tableAttributes[$attribute->getBackendTable()][] = $attributeId;
                    if (!isset($attributeTypes[$attribute->getBackendTable()])) {
                        $attributeTypes[$attribute->getBackendTable()] = $attribute->getBackendType();
                    }
                }
            }
        }

        $selects = [];
        foreach ($tableAttributes as $table => $attributes) {
            $select = $this->_getLoadAttributesSelect($table, $attributes);
            $selects[$attributeTypes[$table]][] = $this->_addLoadAttributesSelectValues(
                $select,
                $table,
                $attributeTypes[$table]
            );
        }
        $selectGroups = $this->_resourceHelper->getLoadAttributesSelectGroups($selects);
        foreach ($selectGroups as $selects) {
            if (!empty($selects)) {
                try {
                    if (is_array($selects)) {
                        $select = implode(' UNION ALL ', $selects);
                    } else {
                        $select = $selects;
                    }
                    $values = $this->getConnection()->fetchAll($select);
                } catch (\Exception $e) {
                    $this->printLogQuery(true, true, $select);
                    throw $e;
                }

                foreach ($values as $value) {
                    $this->_setItemAttributeValue($value);
                }
            }
        }
        if(!empty($mongoAttributes)) {
            $storeId = $this->getStoreId();
            $entityIds = array_keys($this->_itemsById);
            $results = $this->_mongoQuery->getByIds($storeId, $entityIds);
            foreach ($results as $result) {
                $entityIdField = $this->getEntity()->getEntityIdField();
                $entityId = $result[$entityIdField];
                unset($result[$entityIdField]);
                foreach ($result as $attributeId => $attributeValue) {
                    $attributeCode = array_search($attributeId, $this->_selectAttributes);
                    if (!$attributeCode) {
                        $attribute = $this->_eavConfig->getAttribute(
                            $this->getEntity()->getType(),
                            $attributeId
                        );
                        $attributeCode = $attribute->getAttributeCode();
                    }
                    foreach ($this->_itemsById[$entityId] as $object) {
                        $object->setData($attributeCode, $attributeValue);
                    }
                }
            }
        }
        return $this;
    }
}