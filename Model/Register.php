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
use InvalidArgumentException;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\CouldNotSaveException;

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
     * Save Easyship token for a specific store
     *
     * @param string|int $store_id Store ID (will be cast to integer)
     * @param string $token Easyship authentication token
     * @return bool Returns true on successful save
     * @throws CouldNotSaveException When token cannot be saved
     */
    public function saveToken($store_id, $token): bool
    {
        if ($token === null || $token === '') {
            throw new InvalidArgumentException('token is required and cannot be empty');
        }

        if (!is_string($token)) {
            throw new InvalidArgumentException('token must be a string');
        }

        if ($store_id === null || $store_id === '') {
            throw new InvalidArgumentException('store_id is required and cannot be empty');
        }

        // Convert store_id to integer and validate
        $store_id_original = $store_id;
        $store_id = (int)$store_id;
        if ($store_id <= 0) {
            throw new InvalidArgumentException(
                sprintf('Invalid store_id: %s. Store ID must be a positive integer.', $store_id_original)
            );
        }

        try {
            // Save token to Magento configuration
            $this->_config->saveConfig(
                'easyship_options/ec_shipping/token',
                $token,
                'default',
                $store_id
            );

            // Clear configuration cache to ensure changes take effect immediately
            $this->_cacheTypeList->cleanType('config');

            return true;
        } catch (\Exception $e) {
            // Wrap exceptions in CouldNotSaveException for Magento API compatibility
            throw new CouldNotSaveException(
                __('Could not save token for store %1: %2', $store_id, $e->getMessage())
            );
        }
    }
}
