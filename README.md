# Magento 2 Payment Module Cloudwalk InfinitePay

 - [Main Functionalities](#markdown-header-main-functionalities)
 - [Installation](#markdown-header-installation)
 - [Configuration](#markdown-header-configuration)
 - [Specifications](#markdown-header-specifications)
 - [Attributes](#markdown-header-attributes)


## Main Functionalities
InfinitePay Plugin

## Installation

 - Download the files and place in `app/code/Cloudwalk/InfinitePay`
 - Enable the module by running `php bin/magento module:enable Cloudwalk_InfinitePay`
 - Apply database updates by running `php bin/magento setup:upgrade`
 - Flush the cache by running `php bin/magento cache:flush`


### Configuration

 - InfinitePay - payment/infinitepay/
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



