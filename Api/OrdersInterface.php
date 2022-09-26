<?php
namespace Cloudwalk\InfinitePay\Api;
use Magento\Framework\Webapi\Rest\Request;

interface OrdersInterface
{
    /**
     * {@inheritDoc}
     *
     * @param int $id
     * @return String
     * @throws NoSuchEntityException
     */
    public function getStatus(int $id);

    /**
     * {@inheritDoc}
     * @param mixed $filter
     * @return void
     */
    public function callbackStatus(Request $data);
}