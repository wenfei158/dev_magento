<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel\Product\Compare\Item;

use Magento2\MongoCatalog\Helper\LoadMongoAttributes;
use Magento\Framework\App\ObjectManager;

/**
 * Catalog Product Compare Items Resource Collection
 *
 * @since 100.0.2
 */
class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Compare\Item\Collection
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
