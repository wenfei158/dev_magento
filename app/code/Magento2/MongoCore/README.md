# Magento 2 MongoDB Core Module #


# Description

The module provides an integration of **MongoDB** into **Magento 2**.

It has been developed and tested against **Magento 2 CE 2.3.2**.

This module should be deployed on new project with huge catalog (> 3,000,000 products) since it allows significant reduction of the performance inpact of the EAV model by reducing dramatically the number of attributes stored into the database.


# System requirements

## Install MongoDB Server

This Module requires you to install :

 - MongoDB server >= 4.0 : http://docs.mongodb.org/manual/installation/

For development environment a single MongoDB instance deployment is sufficient. If you plan a production environment with a more complicated architecture (ReplicaSet or Sharding), you will add to test it strongly on this architecture before it will go live and at least testing environment should reproduce this architecture.

## Install PHP MongoDB Drive 

The easiest way to install PHP MongoDB Drive is to use the pecl, by these commands:

    - sudo apt install php7.2-dev php-pear php7.2-mongodb
    - sudo pecl install mongodb

About more information of MongoDB PHP driver, you can see http://pecl.php.net/package/mongo


# Use the Module

## Download And Enable This Module

Before using this module, you must enable this module, then upgrade the setup of magento. Specific approach:

```
composer config repositories.magneto2_mongocore vcs https://github.com/wenfei158/magento2_extensions.git
composer require magento2/module-mongoCore
php -f bin/magento module:enable Magento2_MongoCore
php -f bin/magento setup:upgrade
```

## Configuration

To configure this module, you should run the command as follows:

```

```
After running these commands, you can confirm the configuration of the MongoDB server as shown into the app/etc/env.php file :

    'mongodb' => [
        'connection' => [
            'host' => '127.0.0.1',
            'port' => '27017',
            'database' => 'magento',
            'username' => 'magento',
            'password' => 'magento'
        ]
    ]

## Command Usage About this Module

```
php -f bin/magento module:enable Magento2_MongoCore # Enable this Module
php -f bin/magento module:disable Magento2_MongoCore # Disable this Module
php -f bin/magento setup:config:set --mongodb-host="127.0.0.1" --mongodb-port="27017" --mongodb-db="magento" --mongodb-user="magento" --mongodb-password="magento" # Setting for MongoDB
```

# Release Memo

---

## 1.0.0
*(2019-06-30)* 

* Initial the extension
