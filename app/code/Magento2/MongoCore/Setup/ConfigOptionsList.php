<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento2\MongoCore\Setup;

use Magento\Framework\Config\Data\ConfigData;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Setup\ConfigOptionsListInterface;
use Magento\Framework\Setup\Option\TextConfigOption;
use Magento\Framework\App\DeploymentConfig;

/**
 * Deployment configuration options needed for Setup application
 */
class ConfigOptionsList implements ConfigOptionsListInterface
{
    /**
     * Input key for the options
     */
    const INPUT_KEY_MONGODB_HOST = 'mongodb-host';
    const INPUT_KEY_MONGODB_PORT = 'mongodb-port';
    const INPUT_KEY_MONGODB_DB = 'mongodb-db';
    const INPUT_KEY_MONGODB_USER = 'mongodb-user';
    const INPUT_KEY_MONGODB_PASSWORD = 'mongodb-password';
    const INPUT_KEY_MONGODB_URI_OPTIONS = 'mongodb-uri-options';
    const INPUT_KEY_MONGODB_DRIVER_OPTIONS = 'mongodb-driver-options';

    /**
     * Path to the values in the deployment config
     */
    const CONFIG_PATH_MONGODB_HOST = 'mongodb/connection/host';
    const CONFIG_PATH_MONGODB_PORT = 'mongodb/connection/port';
    const CONFIG_PATH_MONGODB_DB = 'mongodb/connection/database';
    const CONFIG_PATH_MONGODB_USER = 'mongodb/connection/username';
    const CONFIG_PATH_MONGODB_PASSWORD = 'mongodb/connection/password';
    const CONFIG_PATH_MONGODB_URI_OPTIONS = 'mongodb/connection/uri_options';
    const CONFIG_PATH_MONGODB_DRIVER_OPTIONS = 'mongodb/connection/driver_options';

    /**
     * Default values
     */
    const DEFAULT_MONGODB_HOST = '';
    const DEFAULT_MONGODB_PORT = '27017';
    const DEFAULT_MONGODB_DB = 'test';
    const DEFAULT_MONGODB_USER = '';
    const DEFAULT_MONGODB_PASSWORD = '';
    const DEFAULT_MONGODB_URI_OPTIONS = '';
    const DEFAULT_MONGODB_DRIVER_OPTIONS = '';

    /**
     * @var ConnectionValidator
     */
    private $connectionValidator;

    /**
     * Constructor
     *
     * @param ConnectionValidator $connectionValidator
     */
    public function __construct(ConnectionValidator $connectionValidator)
    {
        $this->connectionValidator = $connectionValidator;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return [
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_HOST,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                self::CONFIG_PATH_MONGODB_HOST,
                'mongodb server host',
                self::DEFAULT_MONGODB_HOST
            ),
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_PORT,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                self::CONFIG_PATH_MONGODB_PORT,
                'mongodb server port',
                self::DEFAULT_MONGODB_PORT
            ),
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_DB,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                self::CONFIG_PATH_MONGODB_DB,
                'mongodb database name',
                self::DEFAULT_MONGODB_USER
            ),
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_USER,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                self::CONFIG_PATH_MONGODB_USER,
                'mongodb database username',
                self::DEFAULT_MONGODB_USER
            ),
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_PASSWORD,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                self::CONFIG_PATH_MONGODB_PASSWORD,
                'mongodb database password',
                self::DEFAULT_MONGODB_PASSWORD
            ),
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_URI_OPTIONS,
                TextConfigOption::FRONTEND_WIZARD_TEXTAREA,
                self::CONFIG_PATH_MONGODB_URI_OPTIONS,
                'mongodb uri Options (JSON)',
                self::DEFAULT_MONGODB_URI_OPTIONS
            ),
            new TextConfigOption(
                self::INPUT_KEY_MONGODB_DRIVER_OPTIONS,
                TextConfigOption::FRONTEND_WIZARD_TEXTAREA,
                self::CONFIG_PATH_MONGODB_DRIVER_OPTIONS,
                'mongodb driver Options (JSON)',
                self::DEFAULT_MONGODB_DRIVER_OPTIONS
            ),
        ];
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createConfig(array $data, DeploymentConfig $deploymentConfig)
    {
        $configData = new ConfigData(ConfigFilePool::APP_ENV);

        if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_HOST)) {
            $configData->set(self::CONFIG_PATH_MONGODB_HOST, $data[self::INPUT_KEY_MONGODB_HOST]);
            if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_PORT)) {
                $configData->set(self::CONFIG_PATH_MONGODB_PORT, $data[self::INPUT_KEY_MONGODB_PORT]);
            }
            if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_DB)) {
                $configData->set(self::CONFIG_PATH_MONGODB_DB, $data[self::INPUT_KEY_MONGODB_DB]);
            }
            if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_USER)) {
                $configData->set(self::CONFIG_PATH_MONGODB_USER, $data[self::INPUT_KEY_MONGODB_USER]);
            }
            if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_PASSWORD)) {
                $configData->set(self::CONFIG_PATH_MONGODB_PASSWORD, $data[self::INPUT_KEY_MONGODB_PASSWORD]);
            }
            if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_URI_OPTIONS)) {
                $options = json_decode(
                    $data[self::INPUT_KEY_MONGODB_URI_OPTIONS],
                    true
                );
                if ($options !== null) {
                    $configData->set(
                        self::CONFIG_PATH_MONGODB_URI_OPTIONS,
                        $options
                    );
                }
            }
            if (!$this->isDataEmpty($data, self::INPUT_KEY_MONGODB_DRIVER_OPTIONS)) {
                $options = json_decode(
                    $data[self::INPUT_KEY_MONGODB_DRIVER_OPTIONS],
                    true
                );
                if ($options !== null) {
                    $configData->set(
                        self::CONFIG_PATH_MONGODB_DRIVER_OPTIONS,
                        $options
                    );
                }
            }
        }

        return [$configData];
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $options, DeploymentConfig $deploymentConfig)
    {
        $errors = [];

        if (isset($options[self::INPUT_KEY_MONGODB_HOST]) && $options[self::INPUT_KEY_MONGODB_HOST] !== '') {
            if (!$this->isDataEmpty($options,self::INPUT_KEY_MONGODB_URI_OPTIONS)) {
                $uriOptions = json_decode(
                    $options[self::INPUT_KEY_MONGODB_URI_OPTIONS],
                    true
                );
            } else {
                $uriOptions = null;
            }
            if (!$this->isDataEmpty($options,self::INPUT_KEY_MONGODB_DRIVER_OPTIONS)) {
                $driverOptions = json_decode(
                    $options[self::INPUT_KEY_MONGODB_DRIVER_OPTIONS],
                    true
                );
            } else {
                $driverOptions = null;
            }
            $result = $this->connectionValidator->isConnectionValid(
                $options[self::INPUT_KEY_MONGODB_HOST],
                $options[self::INPUT_KEY_MONGODB_PORT],
                $options[self::INPUT_KEY_MONGODB_DB],
                $options[self::INPUT_KEY_MONGODB_USER],
                $options[self::INPUT_KEY_MONGODB_PASSWORD],
                $uriOptions,
                $driverOptions
            );

            if (!$result) {
                $errors[] = "Could not connect to the mongodb Server.";
            }
        }

        return $errors;
    }

    /**
     * Check if data ($data) with key ($key) is empty
     *
     * @param array $data
     * @param string $key
     * @return bool
     */
    private function isDataEmpty(array $data, $key)
    {
        if (isset($data[$key]) && $data[$key] !== '') {
            return false;
        }

        return true;
    }
}
