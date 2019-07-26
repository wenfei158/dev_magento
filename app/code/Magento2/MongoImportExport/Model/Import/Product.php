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
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface as ValidatorInterface;
use Magento\CatalogImportExport\Model\StockItemImporterInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Store\Model\Store;
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
     * Catalog config.
     *
     * @var CatalogConfig
     */
    private $catalogConfig;

    /**
     * Container for filesystem object.
     *
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Provide ability to process and save images during import.
     *
     * @var MediaGalleryProcessor
     */
    private $mediaProcessor;

    /**
     * Product entity link field
     *
     * @var string
     */
    private $productEntityLinkField;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

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
        $this->filesystem = $filesystem;
        $this->_mongoAdapter = $adapter;
        $this->catalogConfig = $catalogConfig ?: ObjectManager::getInstance()->get(CatalogConfig::class);
        $this->mediaProcessor = $mediaProcessor ?: ObjectManager::getInstance()->get(MediaGalleryProcessor::class);
        $this->productRepository = $productRepository ?? ObjectManager::getInstance()
                ->get(ProductRepositoryInterface::class);
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
            $this->filesystem,
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
            $this->catalogConfig,
            $imageTypeProcessor,
            $this->mediaProcessor,
            $stockItemImporter,
            $dateTimeFactory,
            $this->productRepository
        );
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
            if ($tableName == \Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes::BACKEND_TABLE_NAME) {
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
                            [[$entityIdField => $id], ['$set' => array_merge($attributes, [$entityIdField => $id])], ['upsert' => true]]
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

    /**
     * Gather and save information about product entities.
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     * phpcs:disable Generic.Metrics.NestingLevel
     */
    protected function _saveProducts()
    {
        $priceIsGlobal = $this->_catalogData->isPriceGlobal();
        $productLimit = null;
        $productsQty = null;
        $entityLinkField = $this->getProductEntityLinkField();

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = [];
            $entityRowsUp = [];
            $attributes = [];
            $this->websitesCache = [];
            $this->categoriesCache = [];
            $tierPrices = [];
            $mediaGallery = [];
            $labelsForUpdate = [];
            $imagesForChangeVisibility = [];
            $uploadedImages = [];
            $previousType = null;
            $prevAttributeSet = null;
            $existingImages = $this->getExistingImages($bunch);

            foreach ($bunch as $rowNum => $rowData) {
                // reset category processor's failed categories array
                $this->categoryProcessor->clearFailedCategories();

                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }
                $rowScope = $this->getRowScope($rowData);

                $urlKey = $this->getUrlKey($rowData);
                if (!empty($rowData[self::URL_KEY])) {
                    // If url_key column and its value were in the CSV file
                    $rowData[self::URL_KEY] = $urlKey;
                } elseif ($this->isNeedToChangeUrlKey($rowData)) {
                    // If url_key column was empty or even not declared in the CSV file but by the rules it is need to
                    // be setteed. In case when url_key is generating from name column we have to ensure that the bunch
                    // of products will pass for the event with url_key column.
                    $bunch[$rowNum][self::URL_KEY] = $rowData[self::URL_KEY] = $urlKey;
                }

                $rowSku = $rowData[self::COL_SKU];

                if (null === $rowSku) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }

                if (self::SCOPE_STORE == $rowScope) {
                    // set necessary data from SCOPE_DEFAULT row
                    $rowData[self::COL_TYPE] = $this->skuProcessor->getNewSku($rowSku)['type_id'];
                    $rowData['attribute_set_id'] = $this->skuProcessor->getNewSku($rowSku)['attr_set_id'];
                    $rowData[self::COL_ATTR_SET] = $this->skuProcessor->getNewSku($rowSku)['attr_set_code'];
                }

                // 1. Entity phase
                if ($this->isSkuExist($rowSku)) {
                    // existing row
                    if (isset($rowData['attribute_set_code'])) {
                        $attributeSetId = $this->catalogConfig->getAttributeSetId(
                            $this->getEntityTypeId(),
                            $rowData['attribute_set_code']
                        );

                        // wrong attribute_set_code was received
                        if (!$attributeSetId) {
                            throw new LocalizedException(
                                __(
                                    'Wrong attribute set code "%1", please correct it and try again.',
                                    $rowData['attribute_set_code']
                                )
                            );
                        }
                    } else {
                        $attributeSetId = $this->skuProcessor->getNewSku($rowSku)['attr_set_id'];
                    }

                    $entityRowsUp[] = [
                        'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                        'attribute_set_id' => $attributeSetId,
                        $entityLinkField => $this->getExistingSku($rowSku)[$entityLinkField]
                    ];
                } else {
                    if (!$productLimit || $productsQty < $productLimit) {
                        $entityRowsIn[strtolower($rowSku)] = [
                            'attribute_set_id' => $this->skuProcessor->getNewSku($rowSku)['attr_set_id'],
                            'type_id' => $this->skuProcessor->getNewSku($rowSku)['type_id'],
                            'sku' => $rowSku,
                            'has_options' => isset($rowData['has_options']) ? $rowData['has_options'] : 0,
                            'created_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                            'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                        ];
                        $productsQty++;
                    } else {
                        $rowSku = null;
                        // sign for child rows to be skipped
                        $this->getErrorAggregator()->addRowToSkip($rowNum);
                        continue;
                    }
                }

                if (!array_key_exists($rowSku, $this->websitesCache)) {
                    $this->websitesCache[$rowSku] = [];
                }
                // 2. Product-to-Website phase
                if (!empty($rowData[self::COL_PRODUCT_WEBSITES])) {
                    $websiteCodes = explode($this->getMultipleValueSeparator(), $rowData[self::COL_PRODUCT_WEBSITES]);
                    foreach ($websiteCodes as $websiteCode) {
                        $websiteId = $this->storeResolver->getWebsiteCodeToId($websiteCode);
                        $this->websitesCache[$rowSku][$websiteId] = true;
                    }
                } else {
                    $product = $this->retrieveProductBySku($rowSku);
                    if ($product) {
                        $websiteIds = $product->getWebsiteIds();
                        foreach ($websiteIds as $websiteId) {
                            $this->websitesCache[$rowSku][$websiteId] = true;
                        }
                    }
                }

                // 3. Categories phase
                if (!array_key_exists($rowSku, $this->categoriesCache)) {
                    $this->categoriesCache[$rowSku] = [];
                }
                $rowData['rowNum'] = $rowNum;
                $categoryIds = $this->processRowCategories($rowData);
                foreach ($categoryIds as $id) {
                    $this->categoriesCache[$rowSku][$id] = true;
                }
                unset($rowData['rowNum']);

                // 4.1. Tier prices phase
                if (!empty($rowData['_tier_price_website'])) {
                    $tierPrices[$rowSku][] = [
                        'all_groups' => $rowData['_tier_price_customer_group'] == self::VALUE_ALL,
                        'customer_group_id' => $rowData['_tier_price_customer_group'] ==
                        self::VALUE_ALL ? 0 : $rowData['_tier_price_customer_group'],
                        'qty' => $rowData['_tier_price_qty'],
                        'value' => $rowData['_tier_price_price'],
                        'website_id' => self::VALUE_ALL == $rowData['_tier_price_website'] ||
                        $priceIsGlobal ? 0 : $this->storeResolver->getWebsiteCodeToId($rowData['_tier_price_website']),
                    ];
                }

                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                // 5. Media gallery phase
                list($rowImages, $rowLabels) = $this->getImagesFromRow($rowData);
                $storeId = !empty($rowData[self::COL_STORE])
                    ? $this->getStoreIdByCode($rowData[self::COL_STORE])
                    : Store::DEFAULT_STORE_ID;
                $imageHiddenStates = $this->getImagesHiddenStates($rowData);
                foreach (array_keys($imageHiddenStates) as $image) {
                    if (array_key_exists($rowSku, $existingImages)
                        && array_key_exists($image, $existingImages[$rowSku])
                    ) {
                        $rowImages[self::COL_MEDIA_IMAGE][] = $image;
                        $uploadedImages[$image] = $image;
                    }

                    if (empty($rowImages)) {
                        $rowImages[self::COL_MEDIA_IMAGE][] = $image;
                    }
                }

                $rowData[self::COL_MEDIA_IMAGE] = [];

                /*
                 * Note: to avoid problems with undefined sorting, the value of media gallery items positions
                 * must be unique in scope of one product.
                 */
                $position = 0;
                foreach ($rowImages as $column => $columnImages) {
                    foreach ($columnImages as $columnImageKey => $columnImage) {
                        if (!isset($uploadedImages[$columnImage])) {
                            $uploadedFile = $this->uploadMediaFiles($columnImage);
                            $uploadedFile = $uploadedFile ?: $this->getSystemFile($columnImage);
                            if ($uploadedFile) {
                                $uploadedImages[$columnImage] = $uploadedFile;
                            } else {
                                unset($rowData[$column]);
                                $this->addRowError(
                                    ValidatorInterface::ERROR_MEDIA_URL_NOT_ACCESSIBLE,
                                    $rowNum,
                                    null,
                                    null,
                                    ProcessingError::ERROR_LEVEL_NOT_CRITICAL
                                );
                            }
                        } else {
                            $uploadedFile = $uploadedImages[$columnImage];
                        }

                        if ($uploadedFile && $column !== self::COL_MEDIA_IMAGE) {
                            $rowData[$column] = $uploadedFile;
                        }

                        if ($uploadedFile && !isset($mediaGallery[$storeId][$rowSku][$uploadedFile])) {
                            if (isset($existingImages[$rowSku][$uploadedFile])) {
                                $currentFileData = $existingImages[$rowSku][$uploadedFile];
                                if (isset($rowLabels[$column][$columnImageKey])
                                    && $rowLabels[$column][$columnImageKey] !=
                                    $currentFileData['label']
                                ) {
                                    $labelsForUpdate[] = [
                                        'label' => $rowLabels[$column][$columnImageKey],
                                        'imageData' => $currentFileData
                                    ];
                                }

                                if (array_key_exists($uploadedFile, $imageHiddenStates)
                                    && $currentFileData['disabled'] != $imageHiddenStates[$uploadedFile]
                                ) {
                                    $imagesForChangeVisibility[] = [
                                        'disabled' => $imageHiddenStates[$uploadedFile],
                                        'imageData' => $currentFileData
                                    ];
                                }
                            } else {
                                if ($column == self::COL_MEDIA_IMAGE) {
                                    $rowData[$column][] = $uploadedFile;
                                }
                                $mediaGallery[$storeId][$rowSku][$uploadedFile] = [
                                    'attribute_id' => $this->getMediaGalleryAttributeId(),
                                    'label' => isset($rowLabels[$column][$columnImageKey])
                                        ? $rowLabels[$column][$columnImageKey]
                                        : '',
                                    'position' => ++$position,
                                    'disabled' => isset($imageHiddenStates[$columnImage])
                                        ? $imageHiddenStates[$columnImage] : '0',
                                    'value' => $uploadedFile,
                                ];
                            }
                        }
                    }
                }

                // 6. Attributes phase
                $rowStore = (self::SCOPE_STORE == $rowScope)
                    ? $this->storeResolver->getStoreCodeToId($rowData[self::COL_STORE])
                    : 0;
                $productType = isset($rowData[self::COL_TYPE]) ? $rowData[self::COL_TYPE] : null;
                if ($productType !== null) {
                    $previousType = $productType;
                }
                if (isset($rowData[self::COL_ATTR_SET])) {
                    $prevAttributeSet = $rowData[self::COL_ATTR_SET];
                }
                if (self::SCOPE_NULL == $rowScope) {
                    // for multiselect attributes only
                    if ($prevAttributeSet !== null) {
                        $rowData[self::COL_ATTR_SET] = $prevAttributeSet;
                    }
                    if ($productType === null && $previousType !== null) {
                        $productType = $previousType;
                    }
                    if ($productType === null) {
                        continue;
                    }
                }

                $productTypeModel = $this->_productTypeModels[$productType];
                if (!empty($rowData['tax_class_name'])) {
                    $rowData['tax_class_id'] =
                        $this->taxClassProcessor->upsertTaxClass($rowData['tax_class_name'], $productTypeModel);
                }

                if ($this->getBehavior() == Import::BEHAVIOR_APPEND ||
                    empty($rowData[self::COL_SKU])
                ) {
                    $rowData = $productTypeModel->clearEmptyData($rowData);
                }

                $rowData = $productTypeModel->prepareAttributesWithDefaultValueForSave(
                    $rowData,
                    !$this->isSkuExist($rowSku)
                );
                $product = $this->_proxyProdFactory->create(['data' => $rowData]);

                foreach ($rowData as $attrCode => $attrValue) {
                    $attribute = $this->retrieveAttributeByCode($attrCode);

                    if ('multiselect' != $attribute->getFrontendInput() && self::SCOPE_NULL == $rowScope) {
                        // skip attribute processing for SCOPE_NULL rows
                        continue;
                    }
                    $attrId = $attribute->getId();
                    $backModel = $attribute->getBackendModel();
                    $attrTable = $attribute->getBackend()->getTable();
                    $storeIds = [0];
                    if($attrTable == \Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes::BACKEND_TABLE_NAME) {
                        $attrId = $attrCode;
                    }
                    if ('datetime' == $attribute->getBackendType()
                        && (
                            in_array($attribute->getAttributeCode(), $this->dateAttrCodes)
                            || $attribute->getIsUserDefined()
                        )
                    ) {
                        $attrValue = $this->dateTime->formatDate($attrValue, false);
                    } elseif ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                        $attrValue = gmdate(
                            'Y-m-d H:i:s',
                            $this->_localeDate->date($attrValue)->getTimestamp()
                        );
                    } elseif ($backModel) {
                        $attribute->getBackend()->beforeSave($product);
                        $attrValue = $product->getData($attribute->getAttributeCode());
                    }
                    if (self::SCOPE_STORE == $rowScope) {
                        if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                            // check website defaults already set
                            if (!isset($attributes[$attrTable][$rowSku][$attrId][$rowStore])) {
                                $storeIds = $this->storeResolver->getStoreIdToWebsiteStoreIds($rowStore);
                            }
                        } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                            $storeIds = [$rowStore];
                        }
                        if (!$this->isSkuExist($rowSku)) {
                            $storeIds[] = 0;
                        }
                    }
                    foreach ($storeIds as $storeId) {
                        if (!isset($attributes[$attrTable][$rowSku][$attrId][$storeId])) {
                            $attributes[$attrTable][$rowSku][$attrId][$storeId] = $attrValue;
                        }
                    }
                    // restore 'backend_model' to avoid 'default' setting
                    $attribute->setBackendModel($backModel);
                }
            }

            foreach ($bunch as $rowNum => $rowData) {
                if ($this->getErrorAggregator()->isRowInvalid($rowNum)) {
                    unset($bunch[$rowNum]);
                }
            }

            $this->saveProductEntity(
                $entityRowsIn,
                $entityRowsUp
            )->_saveProductWebsites(
                $this->websitesCache
            )->_saveProductCategories(
                $this->categoriesCache
            )->_saveProductTierPrices(
                $tierPrices
            )->_saveMediaGallery(
                $mediaGallery
            )->_saveProductAttributes(
                $attributes
            )->updateMediaGalleryVisibility(
                $imagesForChangeVisibility
            )->updateMediaGalleryLabels(
                $labelsForUpdate
            );

            $this->_eventManager->dispatch(
                'catalog_product_import_bunch_save_after',
                ['adapter' => $this, 'bunch' => $bunch]
            );
        }

        return $this;
    }
    // phpcs:enable

    /**
     * Prepare array with image states (visible or hidden from product page)
     *
     * @param array $rowData
     * @return array
     */
    private function getImagesHiddenStates($rowData)
    {
        $statesArray = [];
        $mappingArray = [
            '_media_is_disabled' => '1'
        ];

        foreach ($mappingArray as $key => $value) {
            if (isset($rowData[$key]) && strlen(trim($rowData[$key]))) {
                $items = explode($this->getMultipleValueSeparator(), $rowData[$key]);

                foreach ($items as $item) {
                    $statesArray[$item] = $value;
                }
            }
        }

        return $statesArray;
    }

    /**
     * Try to find file by it's path.
     *
     * @param string $fileName
     * @return string
     */
    private function getSystemFile($fileName)
    {
        $filePath = 'catalog' . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . $fileName;
        /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $read */
        $read = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

        return $read->isExist($filePath) && $read->isReadable($filePath) ? $fileName : '';
    }

    /**
     * Check if product exists for specified SKU
     *
     * @param string $sku
     * @return bool
     */
    private function isSkuExist($sku)
    {
        $sku = strtolower($sku);
        return isset($this->_oldSku[$sku]);
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
     * Whether a url key is needed to be change.
     *
     * @param array $rowData
     * @return bool
     */
    private function isNeedToChangeUrlKey(array $rowData): bool
    {
        $urlKey = $this->getUrlKey($rowData);
        $productExists = $this->isSkuExist($rowData[self::COL_SKU]);
        $markedToEraseUrlKey = isset($rowData[self::URL_KEY]);
        // The product isn't new and the url key index wasn't marked for change.
        if (!$urlKey && $productExists && !$markedToEraseUrlKey) {
            // Seems there is no need to change the url key
            return false;
        }

        return true;
    }

    /**
     * Update media gallery labels
     *
     * @param array $labels
     * @return void
     */
    private function updateMediaGalleryLabels(array $labels)
    {
        if (!empty($labels)) {
            $this->mediaProcessor->updateMediaGalleryLabels($labels);
        }
    }

    /**
     * Update 'disabled' field for media gallery entity
     *
     * @param array $images
     * @return $this
     */
    private function updateMediaGalleryVisibility(array $images)
    {
        if (!empty($images)) {
            $this->mediaProcessor->updateMediaGalleryVisibility($images);
        }

        return $this;
    }


    /**
     * Get existing product data for specified SKU
     *
     * @param string $sku
     * @return array
     */
    private function getExistingSku($sku)
    {
        return $this->_oldSku[strtolower($sku)];
    }

    /**
     * Retrieve product by sku.
     *
     * @param string $sku
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    private function retrieveProductBySku($sku)
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            return null;
        }
        return $product;
    }
}