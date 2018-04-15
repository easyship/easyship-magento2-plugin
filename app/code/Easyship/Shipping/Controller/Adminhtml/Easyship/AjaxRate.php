<?php

namespace Easyship\Shipping\Controller\Adminhtml\Easyship;

use Magento\Backend\App\Action;

class AjaxRate extends \Magento\Backend\App\Action
{
    protected $_config;
    protected $_storeManager;
    protected $_easyshipApi;

    public function __construct
    (
        Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Config\Model\ResourceModel\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Easyship\Shipping\Model\Api\Request $easyshipApi
    )
    {
        $this->_config = $config;
        $this->_storeManager = $storeManager;
        $this->_easyshipApi = $easyshipApi;
        parent::__construct($context);
    }

    /**
     * Enable and Disable rate
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $status = $this->_easyshipApi->changeRateRequest($params['enable']);
        $this->getResponse()->setBody(json_encode(['status' => $status]));
    }
}