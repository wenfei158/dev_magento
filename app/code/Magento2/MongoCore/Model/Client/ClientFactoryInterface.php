<?php
/**
 * Connection adapter factory interface
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Model\Client;

/**
 * Interface \Magento2\MongoDB\Connection\ClientFactoryInterface
 *
 */
interface ClientFactoryInterface
{
    /**
     * Create connection to MongoDB server
     *
     * @param FactoryOptions $options
     * @return \MongoDB\Client
     * @throws \MongoDB\Exception\InvalidArgumentException
     */
    public function create($options);
}
