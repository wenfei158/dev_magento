<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Model\Client;

use MongoDB\Client as Mongo;

/**
 * Connection adapter factory
 */
class ClientFactory implements ClientFactoryInterface
{

    /**
     * {@inheritdoc}
     */
    public function create($options)
    {
        $host = $options->getHost();
        $port = $options->getPort();
        $user = $options->getUsername();
        $password = $options->getPassword();
        $dbName = $options->getDbName();
        if($user && $password) {
            $uri = "mongodb://${user}:${password}@${host}:${port}";
        } else {
            $uri = "mongodb://${host}:${port}";
        }
        if($dbName) {
            $uri = $uri . "/". $dbName;
        }
        $uriOptions = $options->getUriOptions() ? $options->getUriOptions() : [];
        $driverOptions =  $options->getDriverOptions() ? $options->getDriverOptions() : [];

        $client = new Mongo($uri, $uriOptions, $driverOptions);

        return $client;
    }
}
