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

namespace Goeasyship\Shipping\Model\Api;

use Magento\Framework\DataObject;

class Request
{

    public const BASE_ENDPOINT = 'https://api.easyship.com/';

    public const BASE_SETTINGS_PATH = 'easyship_options/ec_shipping/';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    protected $_config;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ?string
     */
    protected $_token;

    /**
     * @var \Goeasyship\Shipping\Model\Logger\Logger
     */
    protected $logger;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Config\Model\ResourceModel\Config $config
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Goeasyship\Shipping\Model\Logger\Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Config\Model\ResourceModel\Config         $config,
        \Magento\Store\Model\StoreManagerInterface         $storeManager,
        \Goeasyship\Shipping\Model\Logger\Logger           $logger
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_config = $config;
        $this->_storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Registration app
     *
     * @param DataObject $requestBody
     * @return bool|mixed
     */
    public function registrationsRequest($requestBody)
    {
        $endpoint = self::BASE_ENDPOINT . 'api/v1/magento/registrations';

        $result = $this->_doRequest($endpoint, $requestBody, null, false, 'POST');

        return $result;
    }

    /**
     * Return rates
     *
     * @param DataObject $requestBody
     * @return bool|mixed
     */
    public function getQuotes($requestBody)
    {
        $endpoint = self::BASE_ENDPOINT . 'rate/v1/magento';
        $result = $this->_doRequest($endpoint, $requestBody->getData(), null, true);
        return $result;
    }

    /**
     * Do request to endpoint
     *
     * @param string $endpoint
     * @param array $requestBody
     * @param ?array $headers
     * @param bool $isAuth
     * @param string $method
     * @return bool|mixed
     */
    protected function _doRequest($endpoint, array $requestBody, $headers = null, $isAuth = true, $method = 'POST')
    {
        $client = new \Zend_Http_Client($endpoint);
        $client->setMethod($method);

        if ($isAuth) {
            $client->setHeaders('Authorization', 'Bearer ' . $this->getToken());
        }

        if ($headers === null) {
            $client->setHeaders([
                'Content-Type' => 'application/json'
            ]);
        } elseif (is_array($headers)) {
            $client->setHeaders($headers);
        }

        $client->setRawData(json_encode($requestBody), null);

        $response = $client->request($method);

        if (empty($response) || !$response->isSuccessful()) {
            $this->loggerRequest($endpoint, $response->getStatus());
            return false;
        }
        $result = json_decode($response->getBody(), true);

        $this->loggerRequest($endpoint, $response->getStatus(), $result);

        return $result;
    }

    /**
     * Get Token
     *
     * @return string
     */
    protected function getToken()
    {
        if (empty($this->_token)) {
            $this->_token = $this->_scopeConfig->getValue(
                self::BASE_SETTINGS_PATH . 'token',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }

        return $this->_token;
    }

    /**
     * Add line to log file
     *
     * @param string $endpoint
     * @param string $status
     * @param ?array $response
     */
    protected function loggerRequest($endpoint, $status, $response = null)
    {
        $this->logger->info($endpoint . " : " . $status);
        if (is_array($response)) {
            $this->logger->info(json_encode($response));
        } elseif (!empty($response)) {
            $this->logger->info($response);
        }
    }
}
