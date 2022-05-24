<?php

namespace Cloudwalk\InfinitePay\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Cloudwalk\InfinitePay\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;

class AdminConfiguration extends \Magento\Framework\View\Element\Template
{ 
    public function __construct(\Magento\Backend\Block\Template\Context $context, Data $helper, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, array $data = [])
    { 
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /*
    * @return bool
    */
    public function isEnabled()
    {
        return $this->helper->isEnabled();
    } 
    
    /*
    * @return string
    */
    public function getDisplayText()
    {
        return $this->helper->getDisplayText();
    } 
}