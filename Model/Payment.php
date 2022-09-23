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


class Payment extends Cc
{
	const VERSION = '1.0.0';
	const CODE = 'infinitepay';
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
		CustomerRepositoryInterface $customerRepository,
		\Magento\Framework\HTTP\Client\Curl $curl,
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
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
		$isTest = ((int)$this->getConfigData('sandbox') == 1);
		$info = $this->getInfoInstance();
		$paymentInfo = $info->getAdditionalInformation()['additional_data'];
		$paymentMethod = $paymentInfo['payment_method'];

		if($paymentMethod === 'cc') {
			$requestData = $this->buildCreditCardPayload($payment, $paymentInfo, $amount);
		}else{
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
			$payment->setTransactionId($response->data->id);
			$payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$response->data]);

			if($paymentMethod === 'pix') {;
				$payment->setMethod('pix');
			}else{
				$payment->setShouldCloseParentTransaction(true)->setIsTransactionPending(false)->setIsTransactionClosed(true);
			}
			$payment->getOrder()->save();
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
				'nsu' => $this->generate_uuid()
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
					'payer_ip'   => isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']),
				)
			)
		];
	}

	private function buildPixPayload($payment, $paymentInfo, $amount) {
		$order = $payment->getOrder();
        $billing = $order->getBillingAddress();
		
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

		$transactionSecret = sha1( $order->getIncrementId() . time() );

		return [
			'amount' => $this->converToCents($amount),
			'capture_method' =>'pix',
			'origin'         =>'magento',
			'metadata' => array(
				'store_url' => $storeManager->getStore()->getBaseUrl(),
				'plugin_version' => self::VERSION,
				'payment_method' => 'pix',
				'callback' => array(
					'validate' => '',
					'confirm'  => $storeManager->getStore()->getBaseUrl() . '/infinitepay_pix_callback?order_id=' . $order->getIncrementId(),
					'secret'   => $transactionSecret
				),
				'risk'           => array(
					'session_id' => $this->_customerSession->getSessionId(),
					'payer_ip'   => isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']),
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

	private function generate_uuid() {
		$data = openssl_random_pseudo_bytes( 16 );
		assert( strlen( $data ) == 16 );

		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
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
		
        // if (!$parent) {
        //     $this->_helperData->log('CustomPayment::isAvailable - Module not available due to magento rules.');
        //     $status = false;
        // }		
        return $status;
    }

	public function validate()
    {
        AbstractMethod::validate();
        return $this;
    }
}
