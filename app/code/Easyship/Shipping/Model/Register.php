<?php

namespace Easyship\Shipping\Model;


class Register implements \Easyship\Shipping\Api\RegisterInterface
{
    protected $_config;
    protected $_cacheTypeList;

    public function __construct
    (
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Config\Model\ResourceModel\Config $config
    )
    {
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