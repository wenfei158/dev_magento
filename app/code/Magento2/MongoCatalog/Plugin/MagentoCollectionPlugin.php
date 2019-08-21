<?php

namespace Magento2\MongoCatalog\Plugin;

class MagentoCollectionPlugin
{

    private $patchData;

    public function __construct(
        \Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes $patchData
    ) {
        $this->patchData = $patchData;
    }

    public function before_loadAttributes(\Magento\Catalog\Model\ResourceModel\Product\Collection $subject)
    {
        $mongoAttributes = $this->patchData->getMongoAttributesCode();
        foreach ($mongoAttributes as $attributeCode) {
            $subject->removeAttributeToSelect($attributeCode);
        }
    }

    public function beforeAddAttributeToFilter(\Magento\Catalog\Model\ResourceModel\Product\Collection $subject, $attribute, $condition)
    {
        if ($attribute instanceof \Magento\Eav\Model\Entity\Attribute\AbstractAttribute) {
            $attribute = $attribute->getAttributeCode();
            if ($this->patchData->isMongoAttribute($attribute)) {
                $attribute = null;
            }
        } elseif (is_numeric($attribute)) {
            $attributeModel = $subject->getEntity()->getAttribute($attribute);
            if (!$attributeModel) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Invalid attribute identifier for filter (%1)', get_class($attribute))
                );
            }
            $attribute = $attributeModel->getAttributeCode();
            if ($this->patchData->isMongoAttribute($attribute)) {
                $attribute = null;
            }
        } elseif (is_array($attribute)) {
            $newAttribute = [];
            foreach ($attribute as $item) {
                if (!$this->patchData->isMongoAttribute($item['attribute'])) {
                    $newAttribute[] = $item;
                }
            }
            if ($newAttribute) {
                $attribute = $newAttribute;
            } else {
                $attribute = null;
            }
        } elseif (is_string($attribute)) {
            if ($this->patchData->isMongoAttribute($attribute)) {
                $attribute = null;
            }
        }
        return array($attribute, $condition);
    }

    public function beforeAddAttributeToSort(\Magento\Catalog\Model\ResourceModel\Product\Collection $subject, $attribute, $dir)
    {
        if ($this->patchData->isMongoAttribute($attribute)) {
            $attribute = 'sku';
        }
        return array($attribute, $dir);
    }

    public function beforeGetAllAttributeValues($attribute){
        if ($this->patchData->isMongoAttribute($attribute)) {
            throw new \Exception(
                'Can not use Mongo Attribute(' . $attribute . ') in getAllAttributeValues Function in product Collection.'
            );
        }
        return $attribute;
    }
}