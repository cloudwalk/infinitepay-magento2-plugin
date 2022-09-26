<?php
namespace Cloudwalk\InfinitePay\Model;
use Cloudwalk\InfinitePay\Api\OrdersInterface;
use Magento\Framework\Webapi\Rest\Request;

/**
 * Class ProductRepository
 */
class OrdersManagement implements OrdersInterface
{
    const CODE = 'infinitepay';
	protected $_code = self::CODE;

    /**
     * {@inheritDoc}
     *
     * @param string $id
     * @return String
     * @throws NoSuchEntityException
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
     *
     * @param mixed $data
     * @return String
     */
    public function callbackStatus(Request $data) : string
    {
        $orderId = $data->getParam('order_increment_id');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orders = $collection = $objectManager->create('Magento\Sales\Model\Order');
        $order = $orders->loadByIncrementId($orderId);

        $additionalData = $order->getPayment()->getAdditionalInformation();
        
        if (!array_key_exists($this->_code, $additionalData)) {
            return 'order is not infinitepay';
        }

        $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $order->setState($orderState)->setStatus($orderState);
        $order->save();

        return 'success';
    }
}