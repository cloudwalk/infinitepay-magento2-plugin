# Mage2 Module Cloudwalk InfinitePay

    ``cloudwalk/module-infinitepay``

 - [Main Functionalities](#markdown-header-main-functionalities)
 - [Installation](#markdown-header-installation)
 - [Configuration](#markdown-header-configuration)
 - [Specifications](#markdown-header-specifications)
 - [Attributes](#markdown-header-attributes)


## Main Functionalities
InfinitePay Plugin

## Installation
\* = in production please use the `--keep-generated` option

### Type 1: Zip file

 - Unzip the zip file in `app/code/Cloudwalk`
 - Enable the module by running `php bin/magento module:enable Cloudwalk_InfinitePay`
 - Apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer

 - Make the module available in a composer repository for example:
    - private repository `repo.magento.com`
    - public repository `packagist.org`
    - public github repository as vcs
 - Add the composer repository to the configuration by running `composer config repositories.repo.magento.com composer https://repo.magento.com/`
 - Install the module composer by running `composer require cloudwalk/module-infinitepay`
 - enable the module by running `php bin/magento module:enable Cloudwalk_InfinitePay`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`


## Configuration

 - InfinitePay - payment/infinitepay/*

 - enabled (sales/general/enabled)

 - title (sales/general/title)

 - description (sales/general/description)

 - instructions (sales/general/instructions)

 - max_installments (sales/general/max_installments)

 - max_installments_free (sales/general/max_installments_free)

 - api_key (sales/general/api_key)


## Specifications

 - Payment Method
	- InfinitePay


## Attributes



