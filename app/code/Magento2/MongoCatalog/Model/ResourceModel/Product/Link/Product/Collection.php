<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel\Product\Link\Product;

use Magento2\MongoCatalog\Helper\LoadMongoAttributes;
use Magento\Framework\App\ObjectManager;

/**
 * Catalog product linked products collection
 *
 * @since 100.0.2
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection
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
