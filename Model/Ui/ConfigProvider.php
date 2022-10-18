<?php
/**
 * Copyright © Cloudwalk All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Cloudwalk\InfinitePay\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\HTTP\Client\Curl;

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

    protected $_curl;

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
        $this->_curl = new Curl();
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
        $isTest = ((int)$this->config->getValue('sandbox') == 1);

        $pix_value = 0;
        $discount_pix = (float)$this->config->getValue('discount_pix');
        $min_value_pix = (float)$this->config->getValue('min_value_pix') / 100;
        
        if ( $discount_pix && $amount >= $min_value_pix ) {
            $discountValue          = ( $amount * $discount_pix ) / 100;
            $pix_value = number_format( ($amount - $discountValue), 2, ',', '.');
        }


        return [
            'payment' => [
                self::CODE => [
                    'isTest' => (bool)$isTest,
                    'installments' => $this->calculate_installments(),
                    'max_installments' => $this->config->getValue('max_installments'),
                    'max_installments_free' => $this->config->getValue('max_installments_free'),
                    'instructions' => $this->config->getValue('instructions'),
                    'description' => $this->config->getValue('description'),
                    'version' => '1.0.0',
		            'price' => number_format((float)$amount, 2, '.', ''),
                    'jwt' => $this->getJwt($isTest),
                    'url_tokenize' => $this->getUrlTokenize($isTest),
                    'cc_enabled' => (bool)$this->config->getValue('active_creditcard') == '1',
                    'pix_enabled' => (bool)$this->config->getValue('active_pix') == '1',
                    'discount_pix' => $discount_pix,
                    'pix_value' => $pix_value
                ],
            ]
        ];
    }

    private function getUrlTokenize($isTest)
    {
        if($isTest) {
			return 'https://authorizer-staging.infinitepay.io/v2/cards/tokenize';
		}

        return 'https://authorizer.infinitepay.io/v2/cards/tokenize';
    }

    private function getJwt($isTest)
    {
        
        $clientId = $this->config->getValue('client_id');
        $clientSecret = $this->config->getValue('client_secret');
        $url = 'https://api.infinitepay.io/v2/oauth/token';

        if($isTest) {
			$url = 'https://api-staging.infinitepay.io/v2/oauth/token';
		}

        $this->_curl->addHeader('Content-Type', 'application/json');
		$this->_curl->addHeader('Accept', 'application/json');
        $this->_curl->setOption(CURLOPT_HEADER, 0);
		$this->_curl->setOption(CURLOPT_TIMEOUT, 60);
		$this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
		$this->_curl->setOption(CURLOPT_USERAGENT, "InfinitePay Plugin for Magento 2");
        $this->_curl->setOption(CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $data = [
            "grant_type" => "client_credentials",
            "client_id" => $clientId,
            "client_secret" => $clientSecret,
            "scope" => "card_tokenization"
        ];

        $this->_curl->post($url, json_encode($data));
        $response = $this->_curl->getBody();

        $jsonResponse = json_decode($response);

        if(!$jsonResponse || isset($jsonResponse->error)) {
            return "";
        }

        return $jsonResponse->access_token;
    }


    private function calculate_installments(): array {
        $quote = $this->checkoutSession->getQuote();
        $amount = (float)$quote->getGrandTotal();
        $max_installments = (int)$this->config->getValue('max_installments');
        $max_installments_free = (int)$this->config->getValue('max_installments_free');
        
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
				'value'    => number_format( number_format((float)$value, 2, '.', ''), 2, ",", "." ),
				'interest' => $tax,
			);
		}
		return $installments_value;
	}
}
