<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoImportExport\Model\Import;

/**
 * Import entity product model
 *
 * @api
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Product extends \Magento\CatalogImportExport\Model\Import\Product
{
    /**
     * Product entity link field
     *
     * @var string
     */
    private $productEntityLinkField;

    /**
     * Get product entity link field
     *
     * @return string
     * @throws \Exception
     */
    private function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }


    /**
     * Save product attributes.
     *
     * @param array $attributesData
     * @return $this
     * @throws \Exception
     */
    protected function _saveProductAttributes(array $attributesData)
    {
        $linkField = $this->getProductEntityLinkField();
        foreach ($attributesData as $tableName => $skuData) {
            $tableData = [];
            foreach ($skuData as $sku => $attributes) {
                $linkId = $this->_oldSku[strtolower($sku)][$linkField];
                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {
                        $tableData[] = [
                            $linkField => $linkId,
                            'attribute_id' => $attributeId,
                            'store_id' => $storeId,
                            'value' => $storeValue,
                        ];
                    }
                }
            }
            $this->_connection->insertOnDuplicate($tableName, $tableData, ['value']);
        }

        return $this;
    }
}