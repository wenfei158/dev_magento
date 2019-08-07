<?php
namespace Magento2\MongoCatalog\Helper;

class LoadMongoAttributes
{
    private $mongoQuery;

    private $patchData;

    private $mongoAttributesList = [];

    public function __construct(
        \Magento2\MongoCatalog\Setup\Patch\Data\UpdateMongoAttributes $patchData,
        \Magento2\MongoCore\Model\Adapter\Query\Builder\Query $query
    ) {
        $this->patchData = $patchData;
        $this->mongoQuery = $query;
        $this->mongoAttributesList = $this->patchData->getMongoAttributesCode();
    }

    public function load(\Magento\Catalog\Model\ResourceModel\Product\Collection $collection)
    {
        $products = [];
        $storeId = $collection->getStoreId();
        $entityIdField = $collection->getEntity()->getEntityIdField();
        foreach ($collection as $product) {
            $products[$product->getId()] = $product;
        }
        $results = $this->mongoQuery->getByIds($storeId, array_keys($products));
        foreach ($results as $result) {
            $entityId = $result[$entityIdField];
            foreach ($this->mongoAttributesList as $attributeCode) {
                if (isset($result[$attributeCode])) {
                    $products[$entityId]->setData($attributeCode, $result[$attributeCode]);
                }
            }
        }
        return $collection;
    }
}

