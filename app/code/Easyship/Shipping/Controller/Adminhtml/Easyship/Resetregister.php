<?php
/**
 * Easyship.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Easyship.com license that is
 * available through the world-wide-web at this URL:
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Easyship
 * @package     Easyship_Shipping
 * @copyright   Copyright (c) 2018 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

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
    ) {

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

        return $this;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Easyship_Shipping::easyship');
    }
}
