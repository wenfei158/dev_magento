<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Model\ResourceModel\Search;

use Magento2\MongoCatalog\Helper\LoadMongoAttributes;
use Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes as PatchData;
use Magento\Framework\App\ObjectManager;

/**
 * Search collection
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @api
 * @since 100.0.2
 */
class Collection extends \Magento\CatalogSearch\Model\ResourceModel\Search\Collection
{

    /**
     * Check attribute is Text and is Searchable
     *
     * @param \Magento\Catalog\Model\Entity\Attribute $attribute
     * @return boolean
     */
    protected function _isAttributeTextAndSearchable($attribute)
    {
        $attributeCode = $attribute->getAttributeCode();
        $patchData = ObjectManager::getInstance()->get(PatchData::class);
        if ($patchData->isMongoAttribute($attributeCode)) {
            return false;
        }
        if ($attribute->getIsSearchable() && !in_array(
                $attribute->getFrontendInput(),
                ['select', 'multiselect']
            ) && (in_array(
                    $attribute->getBackendType(),
                    ['varchar', 'text']
                ) || $attribute->getBackendType() == 'static')
        ) {
            return true;
        }
        return false;
    }

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