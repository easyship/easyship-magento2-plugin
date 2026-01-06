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

use Goeasyship\Shipping\Model\Logger\Logger;
use Laminas\Http\Client;
use Laminas\Http\Headers;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Request
{

    public const BASE_ENDPOINT = 'https://api.easyship.com/';

    public const BASE_SETTINGS_PATH = 'easyship_options/ec_shipping/';
    /**
     * @var Headers
     */
    protected Headers $headers;
    /**
     * @var Client
     */
    protected Client $client;
    /**
     * @var \Laminas\Http\Request
     */
    protected \Laminas\Http\Request $request;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ?string
     */
    protected $_token;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param \Laminas\Http\Request $request
     * @param Client $client
     * @param Headers $headers
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Config $config,
        StoreManagerInterface $storeManager,
        Logger $logger,
        \Laminas\Http\Request                              $request,
        Client $client,
        Headers $headers
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_config = $config;
        $this->_storeManager = $storeManager;
        $this->logger = $logger;
        $this->request = $request;
        $this->client = $client;
        $this->headers = $headers;
    }

    /**
     * Registration app
     *
     * @param array $requestBody
     * @return bool|mixed
     */
    public function registrationsRequest($requestBody)
    {
        $endpoint = self::BASE_ENDPOINT . 'api/v1/magento/registrations';

        return $this->_doRequest($endpoint, $requestBody, [], false);
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
        return $this->_doRequest($endpoint, $requestBody->getData());
    }

    /**
     * Do request to endpoint
     *
     * @param string $endpoint
     * @param array $requestBody
     * @param array $headers
     * @param bool $isAuth
     * @param string $method
     * @return bool|mixed
     */
    protected function _doRequest($endpoint, array $requestBody, $headers = [], $isAuth = true, $method = 'POST')
    {
        $request = $this->request;
        $request->setUri($endpoint);
        $request->setMethod($method);
        if (empty($headers)) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($isAuth) {
            $headers['Authorization'] = 'Bearer ' . $this->getToken();
        }

        $headers = $this->headers->addHeaders($headers);
        $request->setHeaders($headers);

        $request->setContent(json_encode($requestBody));

        $response = $this->client->send($request);

        if (empty($response) || !$response->isSuccess()) {
            $this->loggerRequest($endpoint, $response->getStatusCode());
            return false;
        }
        $result = json_decode($response->getBody(), true);

        $this->loggerRequest($endpoint, $response->getStatusCode(), $result);

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
                ScopeInterface::SCOPE_STORE
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
        if (is_string($response)) {
            $this->logger->info($response);
        } elseif (!empty($response)) {
            $this->logger->info(json_encode($response));
        }
    }
}
