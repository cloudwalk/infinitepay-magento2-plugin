<?php
namespace Cloudwalk\InfinitePay\Block\OnePage;

use Magento\Checkout\Model\Session as CheckoutSession;

class CustomSuccess extends \Magento\Framework\View\Element\Template
{
    const CODE = 'infinitepay';
	protected $_code = self::CODE;

    public function getPaymentAdditionalData()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $checkoutSession = $objectManager->create('Magento\Checkout\Model\Session');

        $additionalData = $checkoutSession->getLastRealOrder()->getPayment()->getAdditionalInformation();
        if (!array_key_exists($this->_code, $additionalData)) {
            return [];
        }
        
        return $additionalData[$this->_code];
    }
} 