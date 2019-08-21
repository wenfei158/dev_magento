<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Setup;

use Magento2\MongoCore\Model\Client\ClientFactory;
use Magento2\MongoCore\Model\Client\FactoryOptions;


/**
 * Class ConnectionValidator - validates MongoDB related settings
 */
class ConnectionValidator
{
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
     */
    public function __construct(ClientFactory $clientFactory, FactoryOptions $factoryOptions)
    {
        $this->clientFactory = $clientFactory;
        $this->factoryOptions = $factoryOptions;
    }

    /**
     * Checks MongoDB Connection
     *
     * @param string $host
     * @param string $port
     * @param string $dbName
     * @param string $user
     * @param string $password
     * @param array $uriOptions
     * @param array $driverOptions
     * @return bool true if the connection succeeded, false otherwise
     */
    public function isConnectionValid(
        $host,
        $port,
        $dbName,
        $user,
        $password,
        $uriOptions = [],
        $driverOptions = []
    ) {
        try {
            $this->factoryOptions->setHost($host);
            $this->factoryOptions->setPort($port);
            $this->factoryOptions->setDbName($dbName);
            $this->factoryOptions->setUsername($user);
            $this->factoryOptions->setPassword($password);
            $this->factoryOptions->setUriOptions($uriOptions);
            $this->factoryOptions->setDriverOptions($driverOptions);
            $mongo = $this->clientFactory->create($this->factoryOptions);
            $db = $mongo->selectDatabase($this->factoryOptions->getDbName());
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
