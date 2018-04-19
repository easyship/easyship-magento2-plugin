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

namespace Easyship\Shipping\Model;

class Register implements \Easyship\Shipping\Api\RegisterInterface
{
    protected $_config;
    protected $_cacheTypeList;

    public function __construct(
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Config\Model\ResourceModel\Config $config
    ) {

        $this->_config = $config;
        $this->_cacheTypeList = $cacheTypeList;
    }

    public function saveToken($storeId, $token)
    {
        if (!$storeId) {
            return false;
        }
        $this->_config->saveConfig('easyship_options/ec_shipping/token', $token, 'default', $storeId);
        $this->_cacheTypeList->cleanType('config');
    }
}
