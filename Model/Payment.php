<?php
/**
 * Copyright © Cloudwalk All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Cloudwalk\InfinitePay\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Cc;
use Magento\Framework\Model\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Math\Random;
use Magento\Framework\App\RequestInterface;

class Payment extends Cc
{
	const VERSION = '2.0.3';
	const CODE = 'infinitepay';
	
	private $request;

	protected $_code = self::CODE;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_isGateway = true;
	protected $_countryFactory;
	protected $_canSaveCc = false;
	protected $cart = null;
	protected $_customerSession;
	protected $_checkoutSession;
	protected $customerRepository;
	protected $_logger;
	protected $_curl;
	protected $_transactionSecret;

    public function __construct(	
		Session $customerSession,
		CheckoutSession $checkoutSession,
		Context $context,
		Registry $registry,
		ExtensionAttributesFactory $extensionFactory,
		AttributeValueFactory $customAttributeFactory,
		Data $paymentData,
		ScopeConfigInterface $scopeConfig,
		Logger $logger,
		ModuleListInterface $moduleList,
		TimezoneInterface $localeDate,
		CountryFactory $countryFactory,
		Cart $cart, 
		Random $mathRandom,
		CustomerRepositoryInterface $customerRepository,
		\Magento\Framework\HTTP\Client\Curl $curl,
		RequestInterface $httpRequest
		array $data = array()
	) {
		parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data);
		$this->cart = $cart;
		$this->_countryFactory = $countryFactory;
		$this->_checkoutSession = $checkoutSession;
		$this->_curl = $curl;
		$this->customerRepository = $customerRepository;
		$this->_customerSession = $customerSession;	
		$this->_logger = $logger;
		$this->mathRandom = $mathRandom;

		$this->request = $httpRequest;
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
		$isTest = ((int)$this->getConfigData('sandbox') == 1);
		$info = $this->getInfoInstance();
		$paymentInfo = $info->getAdditionalInformation()['additional_data'];
		$paymentMethod = $paymentInfo['payment_method'];
		$order = $payment->getOrder();



		if($paymentMethod === 'cc') {
			$requestData = $this->buildCreditCardPayload($payment, $paymentInfo, $amount);
		}else{
			$this->_transactionSecret = sha1( $order->getIncrementId() . random_bytes(10) );
			$requestData = $this->buildPixPayload($payment, $paymentInfo, $amount);
		}
		

		$response = $this->authRequest($requestData, $isTest, $paymentMethod);
		$this->handleResponse($response, $payment, $paymentMethod);
		
        return $this;
    }

	private function authRequest($request, $isTest, $paymentMethod)
    {
		$url = 'https://api.infinitepay.io/v2/transactions';
		if($isTest) {
			$url = 'https://authorizer-staging.infinitepay.io/v2/transactions';
			if($paymentMethod == 'cc') {
				$this->_curl->addHeader('Env','mock');
			}
		}
	
		$token = $this->getJwt($isTest);
		
		$this->_curl->addHeader('Content-Type', 'application/json');
		$this->_curl->addHeader('Accept', 'application/json');
		$this->_curl->addHeader('Authorization', "Bearer {$token}"); 
		$this->_curl->setOption(CURLOPT_HEADER, 0);
		$this->_curl->setOption(CURLOPT_TIMEOUT, 60);
		$this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
		$this->_curl->setOption(CURLOPT_USERAGENT, "InfinitePay Plugin for Magento 2");
        $this->_curl->setOption(CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		
		$this->_curl->post($url, json_encode($request));
		$response = $this->_curl->getBody();		
		$responseJson = json_decode($response);
		
		if($isTest) { 
			$debug = [
				'url' => $url,
				'token' => $token,
				'request' => $request,
				'header'=> $this->_curl->getHeaders(),
				'response' => $responseJson
			];
			$this->_logger->debug(['infinitepay request', $debug], null, true);
		}

        if (!$responseJson)
        {
			$this->_logger->error(__('Failed authorize request.'));
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed authorize request.'));
        }
		
        return $responseJson;
    }

	private function handleResponse($response, $payment, $paymentMethod) {
		if($response->data->attributes->authorization_code === '00' || $response->data->attributes->authorization_code === '01')
		{
			$order = $payment->getOrder();
			$payment->setTransactionId($response->data->id);
			$additionalData = [
				$this->_code => [
					'payment_method' => $paymentMethod,
					'transaction_secret' =>  $this->_transactionSecret,
					'order_increment_id' => $payment->getOrder()->getIncrementId(),
					'data' => (array)$response->data
				]
			];
			$payment->setAdditionalInformation($additionalData);
			
			
			if($paymentMethod === 'pix') {

				$pix_value = $order->getGrandTotal();
				$amount = $order->getGrandTotal();
				$discount_pix = (float)$this->getConfigData('discount_pix');
				$min_value_pix = (float)$this->getConfigData('min_value_pix') / 100;
				
				if ( $discount_pix && $amount >= $min_value_pix ) {
					$discountValue = ( $amount * $discount_pix ) / 100;
					$pix_value = ($amount - $discountValue);
				}

				$order->setGrandTotal($pix_value);
				$order->save();

				$payment->setMethod('pix');
				$payment->setShouldCloseParentTransaction(true)->setIsTransactionPending(true)->setIsTransactionClosed(false);
			}else{
				$orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
				$payment->setShouldCloseParentTransaction(true)->setIsTransactionPending(false)->setIsTransactionClosed(true);
			}

			$order->save();
			$payment->save();
		} else {
			throw new \Magento\Framework\Exception\LocalizedException(__('Failed authorize request.'));
		}
	}

	private function buildCreditCardPayload($payment, $paymentInfo, $amount) {
		$order = $payment->getOrder();
        $billing = $order->getBillingAddress();
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

		return [
			'payment' => array(
				'amount' => $this->converToCents($amount),
				'capture_method' =>'ecommerce',
				'origin'         =>'magento',
				'payment_method' => 'credit',
				'installments' => (int)$paymentInfo['installments'],
				'nsu' => $this->mathRandom->getUniqueHash()
			),
			'card' => array(
				'cvv' => $payment->getCcCid(), 
				'token' => $paymentInfo['cc_token'], 
				'card_holder_name' => $paymentInfo['cc_holdername'],
			),
			'order'                => array(
				'id'               => (string)$order->getIncrementId(),
				'amount'           => $this->converToCents($amount),
				'items'            => $this->buildOrderItemsPayload($order),
				'delivery_details' => array(
					'email'        => $order->getCustomerEmail(),
					'name'         => $billing->getName(),
					'phone_number' => $billing->getTelephone(),
					'address'      => array(
						'line1'    => $billing->getStreetLine(1),
						'line2'    => $billing->getStreetLine(2),
						'city'     => $billing->getCity(),
						'state'    => $billing->getRegion(),
						'zip'      => $billing->getPostcode(),
						'country'  => $billing->getCountryId()
					)
				)
			),
			'customer'   	      => array(
				'email'           => $order->getCustomerEmail(),
				'first_name' 	   => $billing->getFirstname(),
				'last_name' 	  => $billing->getLastname(),
				'address'  		  => $billing->getStreetLine(1),
				'complement'      => $billing->getStreetLine(2),
				'city'     		  => $billing->getCity(),
				'state'  		  => $billing->getRegion(),
				'zip'    		  => $billing->getPostcode(),
				'country'		  => $billing->getCountryId(),
				'document_number' => $paymentInfo['document_id'],
				'phone_number' 	  => $billing->getTelephone()
			),
			'billing_details'	 => array(
				'address'    	 => array(
				'line1'  		  => $billing->getStreetLine(1),
				'line2'    		  => $billing->getStreetLine(2),
				'city'     		  => $billing->getCity(),
				'state'  		  => $billing->getRegion(),
				'zip'    		  => $billing->getPostcode(),
				'country'		  => $billing->getCountryId()
				)
			),
			'metadata' => array(
				'store_url' => $storeManager->getStore()->getBaseUrl(),
				'plugin_version' => self::VERSION,
				'risk'           => array(
					'session_id' => $this->_customerSession->getSessionId(),
					'payer_ip'   => $this->payer_ip(),
				)
			)
		];
	}

	private function payer_ip() {

		$http_client_ip = $this->request->getServer('HTTP_CLIENT_IP');
		$http_x_foward = $this->request->getServer('HTTP_X_FORWARDED_FOR');
		$remote_addr = $this->request->getServer('REMOTE_ADDR');

		return isset( $http_client_ip ) ? $http_client_ip : ( isset($http_x_foward) ? $http_x_foward : $remote_addr );
	}

	private function buildPixPayload($payment, $paymentInfo, $amount) {
		$order = $payment->getOrder();
        $billing = $order->getBillingAddress();
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

		// Apply discount if it has one
		$totalWDisc = $amount;
		$amount = $order->getGrandTotal();
		$discount_pix = (!$this->getConfigData('discount_pix')) ? (float)$this->getConfigData('discount_pix') : 0;
		$min_value_pix = (!$this->getConfigData('min_value_pix')) ? (float)$this->getConfigData('min_value_pix') : 0;
		
		if ( $discount_pix && $totalWDisc >= $min_value_pix ) {
			$discountValue = ( $totalWDisc * $discount_pix ) / 100;
			$amount = $totalWDisc - $discountValue;

			$order->setGrandTotal($amount);
			$order->save();
		}



		return [
			'amount' => $this->converToCents($order->getGrandTotal()),
			'capture_method' =>'pix',
			'origin'         =>'magento',
			'metadata' => array(
				'store_url' => $storeManager->getStore()->getBaseUrl(),
				'plugin_version' => self::VERSION,
				'payment_method' => 'pix',
				'callback' => array(
					'validate' => '',
					'confirm'  => $storeManager->getStore()->getBaseUrl() . '/rest/V1/infinitepay/orders/pix_callback?order_increment_id=' . $order->getIncrementId(),
					'secret'   => $this->_transactionSecret
				),
				'risk'           => array(
					'session_id' => $this->_customerSession->getSessionId(),
					'payer_ip'   => $this->payer_ip(),
				)
			)
		];
	}

	private function buildOrderItemsPayload($order) {
		$order_items = [];
		if (count($order->getAllVisibleItems()) > 0) {
			foreach ($order->getAllVisibleItems() as $item) {
				$order_items[] = array(
					'id'          => (string)$item->getSku(),
					'description' => $item->getName(),
					'amount'      => (int)preg_replace('/[^0-9]/', '', $item->getOriginalPrice()),
					'quantity'    => (int)$item->getQtyOrdered()
				);
			}
		}

		return $order_items;
	}

	private function getJwt($isTest)
    {
        
        $clientId = $this->getConfigData('client_id');
        $clientSecret = $this->getConfigData('client_secret');
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
            "scope" => "transactions"
        ];

        $this->_curl->post($url, json_encode($data));
        $response = $this->_curl->getBody();

        $jsonResponse = json_decode($response);

        if(!$jsonResponse) {
			$this->_logger->error(__('JWT transactions generate error.'));
            return "";
        }

        
        return $jsonResponse->access_token;
    }

	protected function converToCents($amount) {
		$dollars = str_replace('$', '', $amount);
		return (int)((string)( $dollars * 100 ));
	}

	public function assignData(\Magento\Framework\DataObject $data)
	{
		parent::assignData($data);
		$this->getInfoInstance()->setAdditionalInformation($data->getData());
		return $this;
	}

	public function isAvailable(CartInterface $quote = null) {
		return $this->available($quote);
    }

	public function available(CartInterface $quote = null)
    {
        $parent = parent::isAvailable($quote);
        $status = true;
        return $status;
    }

	public function validate()
    {
        AbstractMethod::validate();
        return $this;
    }

}
