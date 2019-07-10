<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCore\Model\Adapter\Query\Builder;

use Magento2\MongoCore\Model\Adapter\Adapter;

class Query implements QueryInterface
{
    /**
     *
     */
    const PRODUCT_GROUP_ID = 'group_id';

    /**
     * @var Adapter
     */
    private $adapter;

    /**
     * @param Adapter $mongoAdapter
     */
    public function __construct(Adapter $mongoAdapter)
    {
        $this->adapter = $mongoAdapter;
    }

    /**
     * {@inheritdoc}
     */
    public function getByIds($storeId, $entityIds, array $attributeFields = [])
    {
        if (!$entityIds) {
            return [];
        }
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
        $filter = [$entityIdField => ['$in' => $entityIds]];
        $options = $this->setOptions($attributeFields);
        $results = $this->adapter->find((string)$storeId, $filter, $options);
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getByConditions($storeId, $entityIds, array $conditions, array $attributeFields = [])
    {
        if ($entityIds) {
            if (!is_array($entityIds)) {
                $entityIds = [$entityIds];
            }
            $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
            $filter = [$entityIdField => ['$in' => $entityIds]];
        } else {
            $filter = [];
        }
        array_merge($filter, $conditions);
        $options = $this->setOptions($attributeFields);
        $results = $this->adapter->find((string)$storeId, $filter, $options);
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getByGroupId($storeId, $groupId, array $attributeFields = [])
    {
        if (!$groupId) {
            return [];
        }
        $filter = [self::PRODUCT_GROUP_ID => $groupId];
        $options = $this->setOptions($attributeFields);
        $results = $this->adapter->find((string)$storeId, $filter, $options);
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getByGroupConditions($storeId, $groupId, array $conditions, array $attributeFields = [])
    {
        if ($groupId) {
            $filter = [self::PRODUCT_GROUP_ID => $groupId];
        } else {
            $filter = [];
        }
        array_merge($filter, $conditions);
        $options = $this->setOptions($attributeFields);
        $results = $this->adapter->find((string)$storeId, $filter, $options);
        return $results;
    }

    /**
     * @param array $attributeFields
     * @return array
     */
    private function setOptions($attributeFields)
    {
        $options = ["typeMap" => ['root' => 'array', 'document' => 'array']];
        if ($attributeFields) {
            foreach ($attributeFields as $attributeField) {
                $options['projection'][] = [$attributeField => 1];
            }
        }
        return $options;
    }
}
