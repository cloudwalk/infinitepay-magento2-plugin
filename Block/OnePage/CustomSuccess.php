<?php
namespace Cloudwalk\InfinitePay\Block\OnePage;

use Magento\Checkout\Model\Session as CheckoutSession;

class CustomSuccess extends \Magento\Multishipping\Block\Checkout\Success
{
    const CODE = 'infinitepay';
	protected $_code = self::CODE;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Multishipping\Model\Checkout\Type\Multishipping $multishipping,
        array $data = []
    ) {
        parent::__construct($context, $multishipping, $data);
    }

    public function getPaymentAdditionalData()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $checkoutSession = $objectManager->create('Magento\Checkout\Model\Session');

        $order = $checkoutSession->getLastRealOrder();
        $additionalData = $order->getPayment()->getAdditionalInformation();
        
        if (!array_key_exists($this->_code, $additionalData)) {
            return [];
        }
        
        return [
            'order_increment_id' => $order->getIncrementId(),
            'order_total' => $order->getGrandTotal(),
            'order_view_url' => $this->getViewOrderUrl($order->getId()),
            'additional_data' => $additionalData[$this->_code]
        ];
    }
} 