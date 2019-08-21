<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoStore\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Store\Model\ResourceModel\Group\CollectionFactory;
use Magento\Store\Model\ResourceModel\Group\Collection as StoreGroupCollection;
use Magento2\MongoCore\Model\Adapter\Adapter;
use Magento\Framework\App\ObjectManager;

class UpdateStoreCollection implements DataPatchInterface, PatchVersionInterface
{
    /**
     * @var CollectionFactory
     */
    private $storeGroupFactory;

    /**
     * @var Adapter
     */
    private $mongoAdapter;

    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     * @param CollectionFactory $storeGroupFactory
     * @param Adapter $adapter
     */
    public function __construct(
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        Adapter $adapter,
        CollectionFactory $storeGroupFactory = null
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->mongoAdapter = $adapter;
        $this->storeGroupFactory = $storeGroupFactory
            ?: ObjectManager::getInstance()->get(CollectionFactory::class);
    }

    /**
     * Change Stored DB from MySQL to mongoDB for some product attributes
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $collectionNameList = [];
        $collections = $this->mongoAdapter->listCollections();
        foreach ($collections as $collectionInfo) {
            $collectionNameList[] = $collectionInfo['name'];
        }

        /** @var StoreGroupCollection $storeGroupCollection */
        $storeGroupCollection = $this->storeGroupFactory->create();
        foreach ($storeGroupCollection as $storeGroup) {
            $storeId = $storeGroup->getDefaultStoreId();
            if (!in_array($storeId, $collectionNameList)) {
                $this->mongoAdapter->createCollection($storeId);
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
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