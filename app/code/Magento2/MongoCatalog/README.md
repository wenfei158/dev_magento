# Magento 2 Mongo Catalog Module #


# Description

The module processes some product attributes by **MongoDB** instead of **MySQL**.

It has been developed and tested against **Magento 2 CE 2.3.2**, and it must be used with **magento2/module-mongo-core** together.

This module should be deployed on new project with huge catalog (> 3,000,000 products) since it allows significant reduction of the performance impact of the EAV model by reducing dramatically the number of attributes stored into the database.


# Command Usage About this Module

```
php -f bin/magento module:enable Magento2_MongoCatalog # Enable this Module
php -f bin/magento module:disable Magento2_MongoCatalog # Disable this Module
```


# Release Memo

---

## 1.1.0
*(2019-07-16)* 

* Initial the attributes which are saved in MongoDB

## 1.0.0
*(2019-07-09)* 

* Initial the extension