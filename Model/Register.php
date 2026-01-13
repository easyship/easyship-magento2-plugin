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
 * @copyright   Copyright (c) 2022 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Goeasyship\Shipping\Model;

use Goeasyship\Shipping\Api\RegisterInterface;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface;

class Register implements RegisterInterface
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
     * @param TypeListInterface $cacheTypeList
     * @param Config $config
     */
    public function __construct(
        TypeListInterface $cacheTypeList,
        Config $config
    ) {

        $this->_config = $config;
        $this->_cacheTypeList = $cacheTypeList;
    }

    /**
     * Logic for save token
     *
     * @param string $store_id
     * @param string $token
     * @return false|void
     */
    public function saveToken($store_id, $token): false|void
    {
        try {
            if (empty($store_id)) {
                throw new \InvalidArgumentException('store_id is required');
            }

            if (empty($token)) {
                throw new \InvalidArgumentException('token is required');
            }

            $store_id = (int)$store_id;
            if ($store_id <= 0) {
                throw new \InvalidArgumentException('Invalid store_id');
            }

            $this->_config->saveConfig(
                'easyship_options/ec_shipping/token',
                $token,
                'default',
                $store_id
            );
            $this->_cacheTypeList->cleanType('config');

            return true;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(
                __('Could not save token: %1', $e->getMessage())
            );
        }
    }
}
