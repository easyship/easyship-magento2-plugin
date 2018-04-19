<?php
/**
 * Easyship.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Easyship.com license that is
 * available through the world-wide-web at this URL:
 * https://www.easyship.com/license-agreement.html
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Easyship
 * @package     Easyship_Shipping
 * @copyright   Copyright (c) 2018 Easyship (https://www.easyship.com/)
 * @license     https://www.easyship.com/license-agreement.html
 */

namespace Easyship\Shipping\Model\Api;

class Request
{

    const BASE_ENDPOINT = 'https://api-staging.easyship.com/';

    const BASE_SETTINGS_PATH = 'easyship_options/ec_shipping/';

    protected $_scopeConfig;

    protected $_config;

    protected $_storeManager;

    protected $_token;

    protected $logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Config\Model\ResourceModel\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Easyship\Shipping\Model\Logger\Logger $logger
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_config = $config;
        $this->_storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Registration app
     * @param $requestBody
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
     * @param $requestBody
     * @return bool|mixed
     */
    public function getQuotes($requestBody)
    {
        $endpoint = self::BASE_ENDPOINT . 'rate/v1/magento';
        $result = $this->_doRequest($endpoint, $requestBody->getData(), null, true);
        return $result;
    }

    /**
     * @param string $endpoint
     * @param array $requestBody
     * @param null $headers
     * @param bool $isAuth
     * @param string $method
     * @return bool|mixed
     */
    protected function _doRequest(string $endpoint, array $requestBody, $headers = null, $isAuth = true, string $method = 'POST')
    {
        $client = new \Zend_Http_Client($endpoint);
        $client->setMethod($method);

        if ($isAuth) {
            $client->setHeaders('Authorization', 'Bearer ' . $this->getToken());
        }

        if (is_null($headers)) {
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
     * @param $endpoint
     * @param $status
     * @param null $response
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
