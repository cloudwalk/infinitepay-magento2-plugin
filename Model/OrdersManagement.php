<?php
namespace Cloudwalk\InfinitePay\Model;

/**
 * Class OrdersManagement
 */
class OrdersManagement implements \Cloudwalk\InfinitePay\Api\OrdersInterface
{
    const CODE = 'infinitepay';
	protected $_code = self::CODE;

    /**
     * {@inheritDoc}
     * getorder status
     * @param int $id
     * @return string
     */
    public function getStatus(int $id) : string
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orders = $collection = $objectManager->create('Magento\Sales\Model\Order');
        $order = $orders->loadByIncrementId($id);
        
        return $order->getStatus();
    }
 
    /**
     * {@inheritDoc}
     * process callback
     * @param mixed $data
     * @return string
     */
    public function callbackStatus(\Magento\Framework\Webapi\Rest\Request $data) : string
    {
        $orderId = $data->getParam('order_increment_id');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orders = $collection = $objectManager->create('Magento\Sales\Model\Order');
        $order = $orders->loadByIncrementId($orderId);

        $additionalData = $order->getPayment()->getAdditionalInformation();
        
        if (!array_key_exists($this->_code, $additionalData)) {
            return 'order is not infinitepay';
        }
        
        $callbackSignature = $data->getHeader('X-Callback-Signature');
        $transactionSecret = $additionalData[$this->_code]['transaction_secret'];

        $body = json_encode($data->getBodyParams(), JSON_UNESCAPED_LINE_TERMINATORS | JSON_UNESCAPED_UNICODE);
        $hash = hash_hmac('sha256', $body, $transactionSecret);

        if ($hash != $callbackSignature) {
            return 'invalid callback';
        }

        $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $order->setState($orderState)->setStatus($orderState);
        $order->save();

        return 'success';
    }
}