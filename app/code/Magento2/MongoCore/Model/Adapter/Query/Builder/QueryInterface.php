<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Model\Adapter\Query\Builder;

/**
 * @api
 * @since 100.1.0
 */
interface QueryInterface
{
    /**
     * @param int $storeId
     * @param int $entityId
     * @param array $attributeFields
     * @return array
     * @since 100.1.0
     */
    public function getById($storeId, $entityId, array $attributeFields = []);

    /**
     * @param int $storeId
     * @param array $entityIds
     * @param array $attributeFields
     * @return array
     * @since 100.1.0
     */
    public function getByIds($storeId, $entityIds, array $attributeFields = []);

    /**
     * @param int $storeId
     * @param array $entityIds
     * @param array $conditions
     * @param array $attributeFields
     * @return array
     * @since 100.1.0
     */
    public function getByConditions($storeId, $entityIds, array $conditions, array $attributeFields = []);

    /**
     * @param int $storeId
     * @param int $groupId
     * @param array $attributeFields
     * @return array
     * @since 100.1.0
     */
    public function getByGroupId($storeId, $groupId,  array $attributeFields = []);

    /**
     * @param int $storeId
     * @param int $groupId
     * @param array $conditions
     * @param array $attributeFields
     * @return array
     * @since 100.1.0
     */
    public function getByGroupConditions($storeId, $groupId, array $conditions,  array $attributeFields = []);
}
