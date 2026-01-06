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

namespace Goeasyship\Shipping\Controller\Adminhtml\Easyship;

use Exception;
use Goeasyship\Shipping\Model\Api\Request;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\UrlInterface;
use Magento\Integration\Model\ResourceModel\Oauth\Consumer\Collection;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;

class Ajaxregister extends Action
{
    /**
     * @var \Magento\Integration\Model\ResourceModel\Integration\Collection
     */
    protected $_integration;

    /**
     * @var Collection
     */
    protected $_consumer;

    /**
     * @var Session
     */
    protected $_authSession;

    /**
     * @var StoreInformation
     */
    protected $_storeInfo;

    /**
     * @var Store
     */
    protected $_storeManager;

    /**
     * @var \Magento\Integration\Model\ResourceModel\Oauth\Token\Collection
     */
    protected $_token;

    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManagerInterface;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var TypeListInterface
     */
    protected $_cacheTypeList;

    /**
     * @var Request
     */
    protected $_easyshipApi;

    /**
     * @param Context $context
     * @param \Magento\Integration\Model\ResourceModel\Integration\Collection $integration
     * @param Collection $consumer
     * @param \Magento\Integration\Model\ResourceModel\Oauth\Token\Collection $token
     * @param Session $authSession
     * @param StoreInformation $storeInfo
     * @param Store $storeManager
     * @param StoreManagerInterface $storeManagerInterface
     * @param Config $config
     * @param TypeListInterface $cacheTypeList
     * @param ProductMetadataInterface $productMetadata
     * @param Request $easyshipApi
     */
    public function __construct(
        Context $context,
        \Magento\Integration\Model\ResourceModel\Integration\Collection $integration,
        Collection $consumer,
        \Magento\Integration\Model\ResourceModel\Oauth\Token\Collection $token,
        Session $authSession,
        StoreInformation $storeInfo,
        Store $storeManager,
        StoreManagerInterface $storeManagerInterface,
        Config $config,
        TypeListInterface $cacheTypeList,
        ProductMetadataInterface $productMetadata,
        Request $easyshipApi
    ) {
        parent::__construct($context);

        $this->_integration = $integration;
        $this->_consumer = $consumer;
        $this->_authSession = $authSession;
        $this->_storeInfo = $storeInfo;
        $this->_storeManager = $storeManager;
        $this->_token = $token;
        $this->_productMetadata = $productMetadata;
        $this->_storeManagerInterface = $storeManagerInterface;
        $this->_config = $config;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_easyshipApi = $easyshipApi;
    }

    /**
     * Controller for ajax registration
     *
     * @return void
     */
    public function execute()
    {
        $response = [];
        try {
            $params = $this->getRequest()->getParams();
            if (!empty($params)) {
                $request = [];
                $storeId = filter_var($this->getRequest()->getParam('store_id'), FILTER_SANITIZE_SPECIAL_CHARS);

                // get easyship oauth consumer key and secret
                $request['oauth'] = $this->_getOAuthInfo();
                $request['user'] = $this->_getUserInfo();
                $request['company'] = $this->_getCompanyInfo();
                $request['store'] = $this->_getStoreInfo($storeId);

                $response = $this->_easyshipApi->registrationsRequest($request);
                $this->getResponse()->setBody(json_encode($response));
            } else {
                throw new NotFoundException(__('Method not supported'));
            }
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
            $this->getResponse()->setBody(json_encode($response));
        }

        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Status', 200)
            ->setHeader('Content-type', 'application/json', true);
    }

    /**
     * Return easyship integrations keys and tokens
     *
     * @return array|bool
     * @throws Exception
     */
    protected function _getOAuthInfo()
    {
        $response = [];
        $integration = $this->_integration
            ->addFieldToFilter('name', 'easyship')
            ->setPageSize(1)
            ->setCurPage(1)
            ->getLastItem();

        if (empty($integration)) {
            throw new RuntimeException(__('Something was wrong please create easyship integration and activated it'));
        }

        $consumerId = $integration->getConsumerId();
        if (empty($consumerId)) {
            return false;
        }

        $token = $this->_token
            ->addFieldToFilter('consumer_id', $consumerId)
            ->setPageSize(1)
            ->setCurPage(1)
            ->getLastItem();

        if (!$token->getId()) {
            return false;
        }

        $consumer = $this->_consumer->getItemById($consumerId);
        if (!$consumer->getId()) {
            return false;
        }

        $response['token'] = $token->getToken();
        $response['token_secret'] = $token->getSecret();
        $response['consumer_key'] = $consumer->getKey();
        $response['consumer_secret'] = $consumer->getSecret();

        return $response;
    }

    /**
     * Return user information
     *
     * @return array
     * @throws NotFoundException
     */
    protected function _getUserInfo()
    {
        $response = [];
        $user = $this->_authSession->getUser();
        if (!$user->getId()) {
            throw new NotFoundException(__('User session is not found'));
        }

        $response['email'] = $user->getEmail();
        $response['first_name'] = $user->getFirstname();
        $response['last_name'] = $user->getLastname();
        $response['mobile_phone'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_PHONE);

        return $response;
    }

    /**
     * Return company information
     *
     * @return array
     * @throws Exception
     */
    protected function _getCompanyInfo()
    {
        $response = [];

        $response['name'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_NAME);
        $response['country_code'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE);

        if (empty($response['name']) || empty($response['country_code'])) {
            throw new RuntimeException(
                __('Please, fill store name and store country code (System -> General -> Store Information)')
            );
        }

        return $response;
    }

    /**
     * Return store information
     *
     * @param int|null $storeId
     * @return array
     * @throws NotFoundException
     * @throws NoSuchEntityException
     */
    protected function _getStoreInfo($storeId)
    {
        if (!$storeId) {
            throw new NotFoundException(__('Store not found'));
        }

        $response = [];
        $response['id'] = $storeId;
        $response['name'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_NAME);
        $response['url'] = $this->_storeManagerInterface->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $response['version'] = $this->_productMetadata->getVersion();

        return $response;
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
