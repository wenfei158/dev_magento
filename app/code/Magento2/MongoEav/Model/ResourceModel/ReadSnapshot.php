<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoEav\Model\ResourceModel;

use Magento\Eav\Model\Config;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Model\Entity\ScopeResolver;
use Psr\Log\LoggerInterface;

/**
 * Class ReadSnapshot
 */
class ReadSnapshot extends \Magento\Eav\Model\ResourceModel\ReadSnapshot
{
    /**
     * @var Query
     */
    protected $_readHandler;

    /**
     * @param MetadataPool $metadataPool
     * @param ScopeResolver $scopeResolver
     * @param LoggerInterface $logger
     * @param Config $config
     * @param ReadHandler $readHandler
     */
    public function __construct(
        MetadataPool $metadataPool,
        ScopeResolver $scopeResolver,
        LoggerInterface $logger,
        Config $config,
        ReadHandler $readHandler
    ) {
        $this->_readHandler = $readHandler;
        parent::__construct(
            $metadataPool,
            $scopeResolver,
            $logger,
            $config
        );
    }

    /**
     * {@inheritdoc}
     */
    public function execute($entityType, $entityData, $arguments = [])
    {
        return $this->_readHandler->execute($entityType, $entityData, $arguments);
    }
}