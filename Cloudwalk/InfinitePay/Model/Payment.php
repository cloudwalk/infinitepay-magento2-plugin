<?php


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
		array $data = array(),
	) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data);
        $this->cart = $cart;
        $this->_countryFactory = $countryFactory;
		$this->_checkoutSession = $checkoutSession;
		$this->_curl = $curl;
		$this->customerRepository = $customerRepository;
        $this->_customerSession = $customerSession;

		$debug = [
			'sandbox' => $this->getConfigData('sandbox'),
		];

	
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
		$order = $payment->getOrder();
        $billing = $order->getBillingAddress();
		$info = $this->getInfoInstance();
		$paymentInfo = $info->getAdditionalInformation()['additional_data'];

        try
        {
			$order_items = [];
			if (count($order->getAllVisibleItems()) > 0) {
				foreach ($order->getAllVisibleItems() as $item) {
					$order_items[] = array(
						'id'          => (string)$item->getSku(),
						'description' => $item->getName(),
						'amount'      => (int)preg_replace('/[^0-9]/', '', $item->getOriginalPrice()),
						'quantity'    => (int)$item->getQtyOrdered(),
					);
				}
			}
			
            //build array of all necessary details to pass to infinitePay
            $request = [
				'payment' => array(
					'amount' => (int)preg_replace('/[^0-9]/', '', $amount),
					'capture_method' =>'ecommerce',
					'payment_method' => 'credit',
					'installments' => (int)$paymentInfo['installments']
				),
				'card' => array(
					'cvv' => $payment->getCcCid(), 
					'card_number' => $payment->getCcNumber(), 
					'card_holder_name' => $paymentInfo['cc_holdername'],
					'card_expiration_year' => $payment->getCcExpYear(), 
					'card_expiration_month' => sprintf('%02d',$payment->getCcExpMonth()), 
				),
				'order'                => array(
					'id'               => (string)$order->getIncrementId(),
					'amount'           => (int)preg_replace('/[^0-9]/', '', $amount),
					'items'            => $order_items,
					'delivery_details' => array(
						'email'        => $order->getCustomerEmail(),
						'name'         => $billing->getName(),						
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
					'line1'  		  => $billing->getStreetLine(1),
					'line2'    		  => $billing->getStreetLine(2),
					'city'     		  => $billing->getCity(),
					'state'  		  => $billing->getRegion(),
					'zip'    		  => $billing->getPostcode(),
					'country'		  => $billing->getCountryId()
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
				)
            ];

            $response = $this->authRequest($request);
			
			if (isset($response->data->id))
			{ 
				$payment->setTransactionId($response->data->id);
				$payment->setParentTransactionId($response->data->id);
			} else {
				throw new \Magento\Framework\Exception\LocalizedException(__('Failed authorize request.'));
			}
			
        }
        catch(\Exception $e)
        {
            $this->debug($payment->getData() , $e->getMessage());
        }


        $payment->setIsTransactionClosed(0);
        return $this;
    }
	
    public function authRequest($request)
    {

		$url = 'https://authorizer-staging.infinitepay.io/v2/transactions';
		if((int)$this->getConfigData('sandbox') == 1) {
			$url = 'https://authorizer-staging.infinitepay.io/v2/transactions';
			$this->_curl->addHeader('Env','mock');
		}
		
		$token = ($this->getConfigData('sandbox') == 1) ? $this->getConfigData('sandbox_api_key') : $this->getConfigData('api_key');		
		$this->_curl->addHeader('Content-Type', 'application/json');
		$this->_curl->addHeader('Accept', 'application/json');
		$this->_curl->addHeader('Authorization', $token); 
		$this->_curl->setOption(CURLOPT_HEADER, 0);
		$this->_curl->setOption(CURLOPT_TIMEOUT, 60);
		$this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
		$this->_curl->setOption(CURLOPT_USERAGENT, "InfinitePay Plugin for Magento 2");
        $this->_curl->setOption(CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		
		$this->_curl->post($url, json_encode($request));
		$response = $this->_curl->getBody();
		
		$debug = [
			'url' => $url,
			'token' => $token,
			'sandbox' => $this->getConfigData('sandbox'),
			'request' => $request,
			'header'=> $this->_curl->getHeaders(),
			'response' => json_decode($response)
		];

        if (!$response)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__('Failed authorize request.'));
        }
		
        return json_decode($response);
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