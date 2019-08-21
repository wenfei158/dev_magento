<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoStore\Plugin;

use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\ResourceModel\Website as WebsiteResourceModel;

/**
 * Plugin which is listening website resource model to delete mongo collection
 *
 * @see \Magento\Store\Model\ResourceModel\Website
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @package Magento2\MongoStore\Plugin
 */
class Website
{
    /**
     * @var Adapter
     */
    private $mongoAdapter;

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->mongoAdapter = $adapter;
    }

    /**
     * @param WebsiteResourceModel $subject
     * @param WebsiteResourceModel $result
     * @param AbstractModel $website
     * @return WebsiteResourceModel
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterDelete(
        WebsiteResourceModel $subject,
        WebsiteResourceModel $result,
        AbstractModel $website
    ) {
        $storeIds = $website->getStoreIds();
        foreach ($storeIds as $storeId) {
            $this->mongoAdapter->dropCollection((string)$storeId);
        }
        return $result;
    }
}
