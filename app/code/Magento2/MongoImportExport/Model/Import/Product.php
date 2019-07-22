<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoImportExport\Model\Import;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\CatalogImportExport\Model\Import\Product\ImageTypeProcessor;
use Magento\CatalogImportExport\Model\Import\Product\MediaGalleryProcessor;
use Magento\CatalogImportExport\Model\StockItemImporterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento2\MongoCore\Model\Adapter\Adapter;

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
     * MongoDB Adapter
     *
     * @var Adapter
     */
    protected $_mongoAdapter;

    /**
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\ImportExport\Helper\Data $importExportData
     * @param \Magento\ImportExport\Model\ResourceModel\Import\Data $importData
     * @param \Magento\Eav\Model\Config $config
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration
     * @param \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider
     * @param \Magento\Catalog\Helper\Data $catalogData
     * @param \Magento\ImportExport\Model\Import\Config $importConfig
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param \Magento\CatalogImportExport\Model\Import\Product\OptionFactory $optionFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory
     * @param \Magento\CatalogImportExport\Model\Import\Product\Type\Factory $productTypeFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\LinkFactory $linkFactory
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory
     * @param \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $stockResItemFac
     * @param DateTime\TimezoneInterface $localeDate
     * @param DateTime $dateTime
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry
     * @param \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver
     * @param \Magento\CatalogImportExport\Model\Import\Product\SkuProcessor $skuProcessor
     * @param \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor
     * @param \Magento\CatalogImportExport\Model\Import\Product\Validator $validator
     * @param ObjectRelationProcessor $objectRelationProcessor
     * @param TransactionManagerInterface $transactionManager
     * @param \Magento\CatalogImportExport\Model\Import\Product\TaxClassProcessor $taxClassProcessor
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Product\Url $productUrl
     * @param Adapter $adapter
     * @param array $data
     * @param array $dateAttrCodes
     * @param CatalogConfig $catalogConfig
     * @param ImageTypeProcessor $imageTypeProcessor
     * @param MediaGalleryProcessor $mediaProcessor
     * @param StockItemImporterInterface|null $stockItemImporter
     * @param DateTimeFactory $dateTimeFactory
     * @param ProductRepositoryInterface|null $productRepository
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Eav\Model\Config $config,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider,
        \Magento\Catalog\Helper\Data $catalogData,
        \Magento\ImportExport\Model\Import\Config $importConfig,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\CatalogImportExport\Model\Import\Product\OptionFactory $optionFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory,
        \Magento\CatalogImportExport\Model\Import\Product\Type\Factory $productTypeFactory,
        \Magento\Catalog\Model\ResourceModel\Product\LinkFactory $linkFactory,
        \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory,
        \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $stockResItemFac,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        DateTime $dateTime,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver,
        \Magento\CatalogImportExport\Model\Import\Product\SkuProcessor $skuProcessor,
        \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor,
        \Magento\CatalogImportExport\Model\Import\Product\Validator $validator,
        ObjectRelationProcessor $objectRelationProcessor,
        TransactionManagerInterface $transactionManager,
        \Magento\CatalogImportExport\Model\Import\Product\TaxClassProcessor $taxClassProcessor,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\Url $productUrl,
        Adapter $adapter,
        array $data = [],
        array $dateAttrCodes = [],
        CatalogConfig $catalogConfig = null,
        ImageTypeProcessor $imageTypeProcessor = null,
        MediaGalleryProcessor $mediaProcessor = null,
        StockItemImporterInterface $stockItemImporter = null,
        DateTimeFactory $dateTimeFactory = null,
        ProductRepositoryInterface $productRepository = null
    ) {
        $this->_mongoAdapter = $adapter;
        parent::__construct(
            $jsonHelper,
            $importExportData,
            $importData,
            $config,
            $resource,
            $resourceHelper,
            $string,
            $errorAggregator,
            $eventManager,
            $stockRegistry,
            $stockConfiguration,
            $stockStateProvider,
            $catalogData,
            $importConfig,
            $resourceFactory,
            $optionFactory,
            $setColFactory,
            $productTypeFactory,
            $linkFactory,
            $proxyProdFactory,
            $uploaderFactory,
            $filesystem,
            $stockResItemFac,
            $localeDate,
            $dateTime,
            $logger,
            $indexerRegistry,
            $storeResolver,
            $skuProcessor,
            $categoryProcessor,
            $validator,
            $objectRelationProcessor,
            $transactionManager,
            $taxClassProcessor,
            $scopeConfig,
            $productUrl,
            $data,
            $dateAttrCodes,
            $catalogConfig,
            $imageTypeProcessor,
            $mediaProcessor,
            $stockItemImporter,
            $dateTimeFactory,
            $productRepository
        );
    }

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
     * {@inheritdoc}
     */
    protected function _saveProductAttributes(array $attributesData)
    {
        $linkField = $this->getProductEntityLinkField();
        $entityIdField = \Magento\Eav\Model\Entity::DEFAULT_ENTITY_ID_FIELD;
        foreach ($attributesData as $tableName => $skuData) {
            $tableData = [];
            if ($tableName == 'mongo') {
                foreach ($skuData as $sku => $attributes) {
                    $linkId = $this->_oldSku[strtolower($sku)][$linkField];
                    foreach ($attributes as $attributeId => $storeValues) {
                        foreach ($storeValues as $storeId => $storeValue) {
                            $tableData[$storeId][$linkId][$attributeId] = $storeValue;
                        }
                    }
                }
                foreach ($tableData as $collectionId => $documents) {
                    $operations = [];
                    foreach ($documents as $id => $attributes) {
                        $operations[] = ['updateOne' =>
                            [[$entityIdField => $id], array_merge($attributes, [$entityIdField => $id]), ['upsert' => true]]
                        ];
                    }
                    $this->_mongoAdapter->bulkWrite($collectionId, $operations);
                }
            } else {
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
        }

        return $this;
    }
}