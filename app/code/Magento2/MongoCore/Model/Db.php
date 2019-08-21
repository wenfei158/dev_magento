<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Model;

use Magento2\MongoCore\Model\Client\ClientFactory;
use Magento2\MongoCore\Model\Client\FactoryOptions;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ObjectManager;

/**
 * MongoDB Database
 */
class Db
{
    /**
     * @var DeploymentConfig
     */
    private $config;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var FactoryOptions
     */
    private $factoryOptions;

    /**
     * @param ClientFactory $clientFactory
     * @param FactoryOptions $factoryOptions
     * @param DeploymentConfig $config
     */
    public function __construct(
        ClientFactory $clientFactory,
        FactoryOptions $factoryOptions,
        DeploymentConfig $config = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->factoryOptions = $factoryOptions;
        $this->config = $config !== null ? $config : ObjectManager::getInstance()->get(DeploymentConfig::class);
    }

    /**
     * @return \MongoDB\Client
     */
    private function getConnection()
    {
        $host = $this->config->get('mongodb/connection/host');
        $port = $this->config->get('mongodb/connection/port');
        $dbName = $this->config->get('mongodb/connection/database');
        $user = $this->config->get('mongodb/connection/username');
        $password = $this->config->get('mongodb/connection/password');

        $this->factoryOptions->setHost($host);
        $this->factoryOptions->setPort($port);
        $this->factoryOptions->setDbName($dbName);
        $this->factoryOptions->setUsername($user);
        $this->factoryOptions->setPassword($password);

        $client = $this->clientFactory->create($this->factoryOptions);
        return $client;
    }

    /**
     * @return \MongoDB\Database
     */
    public function getDb()
    {
        $dbName = $this->config->get('mongodb/connection/database');
        $mongo = $this->getConnection();
        $db = $mongo->selectDatabase($dbName);

        return $db;
    }
}