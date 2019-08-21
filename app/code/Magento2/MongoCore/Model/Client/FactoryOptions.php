<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento2\MongoCore\Model\Client;

/**
 * Options a connection will be created according to.
 */
class FactoryOptions
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $port;

    /**
     * @var string
     */
    private $dbName = '';

    /**
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @var array|null
     */
    private $uriOptions = [];

    /**
     * @var array|null
     */
    private $driverOptions = [];

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     *
     * @return void
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getPort(): string
    {
        return $this->port;
    }

    /**
     * @param string $port
     *
     * @return void
     */
    public function setPort(string $port)
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * @param string $dbName
     *
     * @return void
     */
    public function setDbName(string $dbName)
    {
        $this->dbName = $dbName;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     *
     * @return void
     */
    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return void
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    /**
     * @return array|null
     */
    public function getUriOptions()
    {
        return $this->uriOptions;
    }

    /**
     * @param array|null $uriOptions
     *
     * @return void
     */
    public function setUriOptions(array $uriOptions = null)
    {
        $this->uriOptions = $uriOptions;
    }

    /**
     * @return array|null
     */
    public function getDriverOptions()
    {
        return $this->driverOptions;
    }

    /**
     * @param array|null $driverOptions
     *
     * @return void
     */
    public function setDriverOptions(array $driverOptions = null)
    {
        $this->driverOptions = $driverOptions;
    }

}
