<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel\Product\Type\Grouped;

use Magento2\MongoCatalog\Helper\LoadMongoAttributes;
use Magento\Framework\App\ObjectManager;

/**
 * Associated products collection.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class AssociatedProductsCollection extends \Magento\GroupedProduct\Model\ResourceModel\Product\Type\Grouped\AssociatedProductsCollection
{
    /**
     * {@inheritdoc}
     */
    protected function _afterLoad()
    {
        $loadMongoHelper = ObjectManager::getInstance()->get(LoadMongoAttributes::class);
        $loadMongoHelper->load($this);
        return parent::_afterLoadData();
    }
}