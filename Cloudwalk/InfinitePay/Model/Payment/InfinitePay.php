<?php
/**
 * Copyright © Cloudwalk All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Cloudwalk\InfinitePay\Model\Payment;

class InfinitePay extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = "infinitepay";

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}

