<?php

namespace Cloudwalk\InfinitePay\Helper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

/**
* Admin configuration paths
*
*/
const IS_ENABLED = 'Cloudwalk_InfinitePay/payment/infinitepay/active';
const TEXT_TITLE = 'Cloudwalk_InfinitePay/payment/infinitepay/instructions';
const TEXT_DESCRIPTION = 'Cloudwalk_InfinitePay/payment/infinitepay/description'; 
/**
* @var \Magento\Framework\App\Config\ScopeConfigInterface
*/
protected $scopeConfig;

/**
* Data constructor
* @param \Magento\Framework\App\Helper\Context $context
* @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
*/
public function __construct(
\Magento\Framework\App\Helper\Context $context,
\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
) {
parent::__construct($context);

}

/**
* @return $isEnabled
*/
public function isEnabled()
{
$isEnabled = $this->scopeConfig->getValue(self::IS_ENABLED, 
\Magento\Store\Model\ScopeInterface::SCOPE_STORE
);

return $isEnabled;
}

/**
* @return $textTitle
*/
public function getTextTitle()
{
$textTitle = $this->scopeConfig->getValue(self::TEXT_TITLE,
\Magento\Store\Model\ScopeInterface::SCOPE_STORE
);

return $textTitle;
}

/**
* @return $textDescription
*/
public function getDisplayText()
{
$textDescription = $this->scopeConfig->getValue(self::TEXT_DESCRIPTION,
\Magento\Store\Model\ScopeInterface::SCOPE_STORE
);

return $textDescription;
}

}