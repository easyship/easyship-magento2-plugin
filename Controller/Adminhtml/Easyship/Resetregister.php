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
 * @category    Goeasyship
 * @package     Goeasyship_Shipping
 * @copyright   Copyright (c) 2026 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Goeasyship\Shipping\Controller\Adminhtml\Easyship;

use Braintree\Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface;

class Resetregister extends Action
{
    /**
     * @var Config
     */
    protected $_config;
    /**
     * @var TypeListInterface
     */
    protected $_cacheTypeList;

    /**
     * @param Context $context
     * @param TypeListInterface $cacheTypeList
     * @param Config $config
     */
    public function __construct(
        Context $context,
        TypeListInterface $cacheTypeList,
        Config $config
    ) {
        parent::__construct($context);

        $this->_config = $config;
        $this->_cacheTypeList = $cacheTypeList;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $storeId = filter_var($this->getRequest()->getParam('store_id', 0), FILTER_SANITIZE_SPECIAL_CHARS);

        try {
            $this->_config->deleteConfig('easyship_options/ec_shipping/token', 'default', $storeId);
            $this->_cacheTypeList->cleanType('config');
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        $this->getResponse()->setBody(json_encode([]));
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Status', 200)
            ->setHeader('Content-type', 'application/json', true);
    }

    /**
     * Checking access rights to the controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Goeasyship_Shipping::easyship');
    }
}
