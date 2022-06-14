<?php
/**
 * Copyright © Cloudwalk All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Cloudwalk\InfinitePay\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\ConfigInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'infinitepay';

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * ConfigProvider constructor.
     * @param ConfigInterface $config
     * @param Session $checkoutSession
     */
    public function __construct(
        ConfigInterface $config,
        Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
	$quote = $this->checkoutSession->getQuote();
        $amount = (float)$quote->getGrandTotal();

        return [
            'payment' => [
                self::CODE => [
                    'isTest' => (bool) ($this->config->getValue('sandbox') == 'test'),
                    'installments' => $this->calculate_installments(),
                    'max_installments' => $this->config->getValue('max_installments'),
                    'max_installments_free' => $this->config->getValue('max_installments_free'),
                    'instructions' => $this->config->getValue('instructions'),
                    'description' => $this->config->getValue('description'),
                    'version' => '0.0.1',
		    'price' => number_format((float)$amount, 2, '.', '')
                ],
            ]
        ];
    }


    private function calculate_installments(): array {
        $quote = $this->checkoutSession->getQuote();
        $amount = (float)$quote->getGrandTotal();
        $max_installments = (int)$this->config->getValue('max_installments');
        $max_installments_free = (int)$this->config->getValue('max_installments_free');
        //TODO: get this tax pay from API.
        $infinite_pay_tax = [1, 1.3390, 1.5041, 1.5992, 1.6630, 1.7057, 2.3454, 2.3053, 2.2755, 2.2490, 2.2306, 2.2111];
        
		$installments_value = [];
		for (
			$i = 1;
			$i <= (int) $max_installments;
			$i ++
		) {
			$tax      = ! ( (int) $max_installments_free >= $i ) && $i > 1;
			$interest = 1;
			if ( $tax ) {
				$interest = $infinite_pay_tax[ $i - 1 ] / 100;
			}
			$value                = ! $tax ? $amount / $i : $amount * ( $interest / ( 1 - pow( 1 + $interest, - $i ) ) );
			$installments_value[] = array(
				'value'    => number_format((float)$value, 2, '.', ''),
				'interest' => $tax,
			);
		}
		return $installments_value;
	}
}
