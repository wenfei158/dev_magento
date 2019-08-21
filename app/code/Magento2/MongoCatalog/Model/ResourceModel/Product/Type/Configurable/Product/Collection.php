<?php
/**
 * Catalog super product link collection
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel\Product\Type\Configurable\Product;

use Magento2\MongoCatalog\Helper\LoadMongoAttributes;
use Magento\Framework\App\ObjectManager;

/**
 * Class Collection
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Collection extends \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection
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
