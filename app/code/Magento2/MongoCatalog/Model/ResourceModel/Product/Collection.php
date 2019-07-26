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
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;

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
     * @var PatchData
     */
    protected $_patchData;

    /**
     * Mongo attributes used for sort.
     *
     * @var array
     */
    protected $_sortMongoAttributes = [];

    /**
     * Mongo attributes used for filter.
     *
     * @var array
     */
    protected $_filterMongoAttributes = [];

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
     * @param PatchData $patchData
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
        PatchData $patchData,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        ProductLimitationFactory $productLimitationFactory = null,
        MetadataPool $metadataPool = null,
        TableMaintainer $tableMaintainer = null,
        PriceTableResolver $priceTableResolver = null,
        DimensionFactory $dimensionFactory = null
    ) {
        $this->_mongoQuery = $mongoQuery;
        $this->_patchData = $patchData;
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
                if($attribute->getBackendTable() == PatchData::BACKEND_TABLE_NAME) {
                    $mongoAttributes[] = $attributeCode;
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
            $results = $this->_mongoQuery->getByIds($storeId, $entityIds, $mongoAttributes);
            foreach ($results as $result) {
                $entityIdField = $this->getEntity()->getEntityIdField();
                $entityId = $result[$entityIdField];
                foreach ($mongoAttributes as $attributeCode) {
                    if (isset($result[$attributeCode])) {
                        foreach ($this->_itemsById[$entityId] as $object) {
                            $object->setData($attributeCode, $result[$attributeCode]);
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Retrieve max value by attribute
     *
     * @param string $attribute
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMaxAttributeValue($attribute)
    {
        if($this->isMongoAttribute($attribute)) {
            throw new \Exception(
                'Can not use Mongo Attribute(' . $attribute . ') in getMaxAttributeValue Function in product Collection.'
            );
        }
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);
        $attributeCode = $attribute->getAttributeCode();
        $tableAlias = $attributeCode . '_max_value';
        $fieldAlias = 'max_' . $attributeCode;
        $condition = 'e.entity_id = ' . $tableAlias . '.entity_id AND ' . $this->_getConditionSql(
                $tableAlias . '.attribute_id',
                $attribute->getId()
            );

        $select->join(
            [$tableAlias => $attribute->getBackend()->getTable()],
            $condition,
            [$fieldAlias => new \Zend_Db_Expr('MAX(' . $tableAlias . '.value)')]
        )->group(
            'e.entity_type_id'
        );

        $data = $this->getConnection()->fetchRow($select);
        if (isset($data[$fieldAlias])) {
            return $data[$fieldAlias];
        }

        return null;
    }

    /**
     * Retrieve ranging product count for arrtibute range
     *
     * @param string $attribute
     * @param int $range
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributeValueCountByRange($attribute, $range)
    {
        if($this->isMongoAttribute($attribute)) {
            throw new \Exception(
                'Can not use Mongo Attribute(' . $attribute . ') in getAttributeValueCountByRange Function in product Collection.'
            );
        }
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);
        $attributeCode = $attribute->getAttributeCode();
        $tableAlias = $attributeCode . '_range_count_value';

        $condition = 'e.entity_id = ' . $tableAlias . '.entity_id AND ' . $this->_getConditionSql(
                $tableAlias . '.attribute_id',
                $attribute->getId()
            );

        $select->reset(\Magento\Framework\DB\Select::GROUP);
        $select->join(
            [$tableAlias => $attribute->getBackend()->getTable()],
            $condition,
            [
                'count_' . $attributeCode => new \Zend_Db_Expr('COUNT(DISTINCT e.entity_id)'),
                'range_' . $attributeCode => new \Zend_Db_Expr('CEIL((' . $tableAlias . '.value+0.01)/' . $range . ')')
            ]
        )->group(
            'range_' . $attributeCode
        );

        $data = $this->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['range_' . $attributeCode]] = $row['count_' . $attributeCode];
        }
        return $res;
    }

    /**
     * Retrieve product count by some value of attribute
     *
     * @param string $attribute
     * @return array ($value => $count)
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAttributeValueCount($attribute)
    {
        if($this->isMongoAttribute($attribute)) {
            throw new \Exception(
                'Can not use Mongo Attribute(' . $attribute . ') in getAttributeValueCount Function in product Collection.'
            );
        }
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);
        $attributeCode = $attribute->getAttributeCode();
        $tableAlias = $attributeCode . '_value_count';

        $select->reset(\Magento\Framework\DB\Select::GROUP);
        $condition = 'e.entity_id=' . $tableAlias . '.entity_id AND ' . $this->_getConditionSql(
                $tableAlias . '.attribute_id',
                $attribute->getId()
            );

        $select->join(
            [$tableAlias => $attribute->getBackend()->getTable()],
            $condition,
            [
                'count_' . $attributeCode => new \Zend_Db_Expr('COUNT(DISTINCT e.entity_id)'),
                'value_' . $attributeCode => new \Zend_Db_Expr($tableAlias . '.value')
            ]
        )->group(
            'value_' . $attributeCode
        );

        $data = $this->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['value_' . $attributeCode]] = $row['count_' . $attributeCode];
        }
        return $res;
    }

    /**
     * Return all attribute values as array in form:
     * array(
     *   [entity_id_1] => array(
     *          [store_id_1] => store_value_1,
     *          [store_id_2] => store_value_2,
     *          ...
     *          [store_id_n] => store_value_n
     *   ),
     *   ...
     * )
     *
     * @param string $attribute attribute code
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAllAttributeValues($attribute)
    {
        if($this->isMongoAttribute($attribute)) {
            throw new \Exception(
                'Can not use Mongo Attribute(' . $attribute . ') in getAllAttributeValues Function in product Collection.'
            );
        }
        /** @var $select \Magento\Framework\DB\Select */
        $select = clone $this->getSelect();
        $attribute = $this->getEntity()->getAttribute($attribute);

        $fieldMainTable = $this->getConnection()->getAutoIncrementField($this->getMainTable());
        $fieldJoinTable = $attribute->getEntity()->getLinkField();
        $select->reset()
            ->from(
                ['cpe' => $this->getMainTable()],
                ['entity_id']
            )->join(
                ['cpa' => $attribute->getBackend()->getTable()],
                'cpe.' . $fieldMainTable . ' = cpa.' . $fieldJoinTable,
                ['store_id', 'value']
            )->where('attribute_id = ?', (int)$attribute->getId());

        $data = $this->getConnection()->fetchAll($select);
        $res = [];

        foreach ($data as $row) {
            $res[$row['entity_id']][$row['store_id']] = $row['value'];
        }

        return $res;
    }

    /**
     * Add attribute to filter
     *
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute|string|array $attribute
     * @param array $condition
     * @param string $joinType
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @throws \Zend_Db_Select_Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addAttributeToFilter($attribute, $condition = null, $joinType = 'inner')
    {
        if ($this->isEnabledFlat()) {
            if ($attribute instanceof \Magento\Eav\Model\Entity\Attribute\AbstractAttribute) {
                $attribute = $attribute->getAttributeCode();
            }

            if (is_array($attribute)) {
                $sqlArr = [];
                foreach ($attribute as $condition) {
                    $sqlArr[] = $this->_getAttributeConditionSql($condition['attribute'], $condition, $joinType);
                }
                $conditionSql = '(' . join(') OR (', $sqlArr) . ')';
                $this->getSelect()->where($conditionSql);
                return $this;
            }

            if (!isset($this->_selectAttributes[$attribute])) {
                $this->addAttributeToSelect($attribute);
            }

            if (isset($this->_selectAttributes[$attribute])) {
                $this->getSelect()->where($this->_getConditionSql('e.' . $attribute, $condition));
            }

            return $this;
        }

        $this->_allIdsCache = null;

        if (is_string($attribute) && $attribute == 'is_saleable') {
            $columns = $this->getSelect()->getPart(\Magento\Framework\DB\Select::COLUMNS);
            foreach ($columns as $columnEntry) {
                list($correlationName, $column, $alias) = $columnEntry;
                if ($alias == 'is_saleable') {
                    if ($column instanceof \Zend_Db_Expr) {
                        $field = $column;
                    } else {
                        $connection = $this->getSelect()->getConnection();
                        if (empty($correlationName)) {
                            $field = $connection->quoteColumnAs($column, $alias, true);
                        } else {
                            $field = $connection->quoteColumnAs([$correlationName, $column], $alias, true);
                        }
                    }
                    $this->getSelect()->where("{$field} = ?", $condition);
                    break;
                }
            }

            return $this;
        } else {
            if ($attribute === null) {
                $this->getSelect();
                return $this;
            }

            if (is_numeric($attribute)) {
                $attributeModel = $this->getEntity()->getAttribute($attribute);
                if (!$attributeModel) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Invalid attribute identifier for filter (%1)', get_class($attribute))
                    );
                }
                $attribute = $attributeModel->getAttributeCode();
            } elseif ($attribute instanceof \Magento\Eav\Model\Entity\Attribute\AttributeInterface) {
                $attribute = $attribute->getAttributeCode();
            }

            //TODO Make this able processed.
            $conditionSql = '';
            if (is_array($attribute)) {
                $sqlArr = [];
                foreach ($attribute as $condition) {
                    if($this->isMongoAttribute($attribute)) {
                        $this->_filterMongoAttributes[$attribute] = $condition;
                        throw new \Exception(
                            'No use Mongo Attribute(' . $attribute . ') in addAttributeToFilter Function in product Collection.'
                        );
                    } else {
                        $sqlArr[] = $this->_getAttributeConditionSql($condition['attribute'], $condition, $joinType);
                    }
                }
                if($sqlArr) {
                    $conditionSql = '(' . implode(') OR (', $sqlArr) . ')';
                }
            } elseif (is_string($attribute)) {
                if ($condition === null) {
                    $condition = '';
                }
                if ($this->isMongoAttribute($attribute)) {
                    $this->_filterMongoAttributes[$attribute] = $condition;
                    throw new \Exception(
                        'No use Mongo Attribute(' . $attribute . ') in addAttributeToFilter Function in product Collection.'
                    );
                } else {
                    $conditionSql = $this->_getAttributeConditionSql($attribute, $condition, $joinType);
                }
            }

            if (!empty($conditionSql)) {
                $this->getSelect()->where($conditionSql, null, \Magento\Framework\DB\Select::TYPE_CONDITION);
                $this->_totalRecords = null;
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Invalid attribute identifier for filter (%1)', get_class($attribute))
                );
            }

            return $this;
        }
    }

    /**
     * Add attribute to sort order
     *
     * @param string $attribute
     * @param string $dir
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addAttributeToSort($attribute, $dir = self::SORT_ORDER_ASC)
    {
        if ($attribute == 'position') {
            if (isset($this->_joinFields[$attribute])) {
                $this->getSelect()->order($this->_getAttributeFieldName($attribute) . ' ' . $dir);
                return $this;
            }
            if ($this->isEnabledFlat()) {
                $this->getSelect()->order("cat_index_position {$dir}");
            }
            // optimize if using cat index
            $filters = $this->_productLimitationFilters;
            if (isset($filters['category_id']) || isset($filters['visibility'])) {
                $this->getSelect()->order('cat_index.position ' . $dir);
            } else {
                $this->getSelect()->order('e.entity_id ' . $dir);
            }

            return $this;
        } elseif ($attribute == 'is_saleable') {
            $this->getSelect()->order("is_salable " . $dir);
            return $this;
        }

        $storeId = $this->getStoreId();
        if ($attribute == 'price' && $storeId != 0) {
            $this->addPriceData();
            if ($this->_productLimitationFilters->isUsingPriceIndex()) {
                $this->getSelect()->order("price_index.min_price {$dir}");
                return $this;
            }
        }

        //TODO Make this able processed.
        if($this->isMongoAttribute($attribute)) {
            $this->_sortMongoAttributes[$attribute] = $dir;
            throw new \Exception(
                'No use Mongo Attribute(' . $attribute . ') in addAttributeToSort Function in product Collection.'
            );
            return this;
        }

        if ($this->isEnabledFlat()) {
            $column = $this->getEntity()->getAttributeSortColumn($attribute);

            if ($column) {
                $this->getSelect()->order("e.{$column} {$dir}");
            } elseif (isset($this->_joinFields[$attribute])) {
                $this->getSelect()->order($this->_getAttributeFieldName($attribute) . ' ' . $dir);
            }

            return $this;
        } else {
            $attrInstance = $this->getEntity()->getAttribute($attribute);
            if ($attrInstance && $attrInstance->usesSource()) {
                $attrInstance->getSource()->addValueSortToCollection($this, $dir);
                return $this;
            }
        }

        if (isset($this->_joinFields[$attribute])) {
            $this->getSelect()->order($this->_getAttributeFieldName($attribute) . ' ' . $dir);
            return $this;
        }
        if (isset($this->_staticFields[$attribute])) {
            $this->getSelect()->order("e.{$attribute} {$dir}");
            return $this;
        }
        if (isset($this->_joinAttributes[$attribute])) {
            $attrInstance = $this->_joinAttributes[$attribute]['attribute'];
            $entityField = $this->_getAttributeTableAlias($attribute) . '.' . $attrInstance->getAttributeCode();
        } else {
            $attrInstance = $this->getEntity()->getAttribute($attribute);
            $entityField = 'e.' . $attribute;
        }

        if ($attrInstance) {
            if ($attrInstance->getBackend()->isStatic()) {
                $orderExpr = $entityField;
            } else {
                $this->_addAttributeJoin($attribute, 'left');
                if (isset($this->_joinAttributes[$attribute]) || isset($this->_joinFields[$attribute])) {
                    $orderExpr = $attribute;
                } else {
                    $orderExpr = $this->_getAttributeTableAlias($attribute) . '.value';
                }
            }

            if (in_array($attrInstance->getFrontendClass(), $this->_castToIntMap)) {
                $orderExpr = new \Zend_Db_Expr("CAST({$this->_prepareOrderExpression($orderExpr)} AS SIGNED)");
            }

            $orderExpr .= ' ' . $dir;
            $this->getSelect()->order($orderExpr);
        }
        return $this;
    }

    /**
     * Determine whether to save in mongodb
     *
     * @param string $attributeCode
     * @return boolean
     */
    public function isMongoAttribute($attributeCode) {
        $MongoAttributesCodeList = $this->_patchData->getMongoAttributesCode();
        if (in_array($attributeCode, $MongoAttributesCodeList)) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve is flat enabled. Return always false if magento run admin.
     * In this time, Use Mongo instead of flat. Return always false.
     *
     * @return bool
     */
    public function isEnabledFlat()
    {
        return false;
    }
}