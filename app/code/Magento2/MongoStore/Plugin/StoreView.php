<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoStore\Plugin;

use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\ResourceModel\Store as StoreResourceModel;

/**
 * Plugin which is listening store resource model and to create or to delete mongo collection
 *
 * @see \Magento\Store\Model\ResourceModel\Store
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @package Magento2\MongoStore\Plugin
 */
class StorePlugin
{
    /**
     * @var Adapter
     */
    private $mongoAdapter;

    /**
     * @var AbstractModel
     */
    private $origStore;

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->mongoAdapter = $adapter;
    }

    /**
     * @param StoreResourceModel $object
     * @param AbstractModel $store
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(
        StoreResourceModel $object,
        AbstractModel $store
    ) {
        $this->origStore = $store;
    }

    /**
     * Create mongo collection on store after save
     *
     * @param StoreResourceModel $object
     * @param StoreResourceModel $storeResourceModel
     * @return StoreResourceModel
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(
        StoreResourceModel $object,
        StoreResourceModel $storeResourceModel
    ) {
        if ($this->origStore->isObjectNew() || $this->origStore->dataHasChangedFor('group_id')) {
            $storeId = $this->origStore->getId();
            $this->mongoAdapter->createCollection($storeId);
        }
        return $storeResourceModel;
    }

    /**
     * Delete mongo collection on store after delete
     *
     * @param StoreResourceModel $subject
     * @param StoreResourceModel $result
     * @param AbstractModel $store
     * @return StoreResourceModel
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDelete(
        StoreResourceModel $subject,
        StoreResourceModel $result,
        AbstractModel $store
    ) {
        $storeId = $store->getId();
        $this->mongoAdapter->dropCollection((string)$storeId);
        return $result;
    }
}
