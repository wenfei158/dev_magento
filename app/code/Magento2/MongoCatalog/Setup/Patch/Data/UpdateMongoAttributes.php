<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCatalog\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class UpdateMongoAttributes implements DataPatchInterface, PatchVersionInterface
{
    const BACKEND_TABLE_NAME = "mongo";

    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var array
     */
    private $mongoAttributes = [
        'name',
        'meta_title',
        'meta_description',
        'meta_keyword',
        'brand',
        'mfr_no',
        'package_quantity',
        'custom_country_of_origin',
        'search_keywords',
        'description',
        'short_description',
        'variant_attributes',
        'images',
        'videos'
    ];

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Change Stored DB from MySQL to mongoDB for some product attributes
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entityTypeId = $eavSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);
        foreach ($this->mongoAttributes as $attributeCode) {
            $attribute = $eavSetup->getAttribute($entityTypeId, $attributeCode);
            if (isset($attribute['attribute_id'])) {
                $eavSetup->updateAttribute($entityTypeId, $attribute['attribute_id'], 'backend_table', self::BACKEND_TABLE_NAME);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
    * Get Mongo Attribute Code list
    */
    public function getMongoAttributesCode()
    {
        return $this->mongoAttributes;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion()
    {
        return '1.1.0';
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}