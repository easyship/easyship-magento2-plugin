<?php

namespace Easyship\Shipping\Controller\Adminhtml\Easyship;

use Braintree\Exception;

class Resetregister extends \Magento\Backend\App\Action
{
    protected $_config;
    protected $_cacheTypeList;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Config\Model\ResourceModel\Config $config
    )
    {
        parent::__construct($context);

        $this->_config = $config;
        $this->_cacheTypeList = $cacheTypeList;
    }

    public function execute()
    {
        $storeId = filter_var($this->getRequest()->getParam('store_id'), FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$storeId) {
            return false;
        }
        try {
            $this->_config->deleteConfig('easyship_options/ec_shipping/token', 'default', $storeId);
            $this->_cacheTypeList->cleanType('config');
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function _isAllowed()
    {
        return true;
    }
}