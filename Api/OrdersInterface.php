<?php
namespace Cloudwalk\InfinitePay\Api;

interface OrdersInterface
{
    /**
     * {@inheritDoc}
     * GET order status
     * @param int $id
     * @return string
     */
    public function getStatus(int $id);

    /**
     * {@inheritDoc}
     * POST callback
     * @return string
     */
    public function callbackStatus();
}