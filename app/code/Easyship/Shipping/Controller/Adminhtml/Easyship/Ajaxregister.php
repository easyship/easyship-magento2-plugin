<?php

namespace Easyship\Shipping\Controller\Adminhtml\Easyship;

use Magento\Store\Model\Information as StoreInformation;

class Ajaxregister extends \Magento\Backend\App\Action
{
    protected $_integration;
    protected $_consumer;
    protected $_authSession;
    protected $_storeInfo;
    protected $_storeManager;
    protected $_token;
    protected $_productMetadata;
    protected $_storeManagerInterface;
    protected $_config;
    protected $_cacheTypeList;
    protected $_easyshipApi;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Integration\Model\ResourceModel\Integration\Collection $integration,
        \Magento\Integration\Model\ResourceModel\Oauth\Consumer\Collection $consumer,
        \Magento\Integration\Model\ResourceModel\Oauth\Token\Collection $token,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Store\Model\Information $storeInfo,
        \Magento\Store\Model\Store $storeManager,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Config\Model\ResourceModel\Config $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Easyship\Shipping\Model\Api\Request $easyshipApi
    )
    {
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

    public function execute()
    {
        $response = [];
        try {
            $params = $this->getRequest()->getParams();
            if (count($params)) {
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
                throw new \Exception('Method not supported');
            }
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
            $this->getResponse()->setBody(json_encode($response));
        }

        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Status', 200)
            ->setHeader('Content-type', 'application/json', true);
    }

    /**
     * @return array|bool
     * @throws \Exception
     */
    protected function _getOAuthInfo()
    {
        $response = [];
        $integration = $this->_integration
            ->addFieldToFilter('name', 'easyship')
            ->getFirstItem();

        if (empty($integration)) {
            throw new \Exception('Something was wrong please create easyship integration and activated it');
        }

        $consumerId = $integration->getConsumerId();
        if (!$consumerId) {
            return false;
        }

        $token = $this->_token
            ->addFieldToFilter('consumer_id', $consumerId)
            ->getFirstItem();

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
     * @return array
     * @throws \Exception
     */
    protected function _getUserInfo()
    {
        $response = [];
        $user = $this->_authSession->getUser();
        if (!$user->getId()) {
            throw new \Exception('User session is not found');
        }

        $response['email'] = $user->getEmail();;
        $response['first_name'] = $user->getFirstname();
        $response['last_name'] = $user->getLastname();
        $response['mobile_phone'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_PHONE);

        return $response;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function _getCompanyInfo()
    {
        $response = [];

        $response['name'] = $store = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_NAME);
        $response['country_code'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE);

        if (empty($response['name']) || empty($response['country_code'])) {
            throw new \Exception('Please, fill store name and store country code (System -> General -> Store Information)');
        }

        return $response;
    }

    /**
     * @param $storeId
     * @return array
     * @throws \Exception
     */
    protected function _getStoreInfo($storeId)
    {
        if (!$storeId) {
            throw new \Exception('store not found');
        }

        $response = array();
        $response['id'] = $storeId;
        $response['name'] = $this->_storeManager->getConfig(StoreInformation::XML_PATH_STORE_INFO_NAME);
        $response['url'] = $this->_storeManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $response['version'] = $this->_productMetadata->getVersion();

        return $response;

    }

    protected function _testDoRequest()
    {
        return [

            "registration" => [
                "id" => "903b9e67-ea6b-4173-919a-2550e79a5274",
                "flow" => "magento",
                "current_step" => "welcome",
                "last_step_reached" => "welcome",
                "complete" => "false",
                "accessible_steps" => [
                    "welcome",
                    "company",
                    "address",
                    "in-cart",
                    "success",
                ],
                "event_logs" => [],
                "data" => [
                    "user" => [
                        "first_name" => "Anna",
                        "last_name" => "Holubiatnikova",
                        "mobile_phone" => "NULL",
                        "email" => "zewisa@gmail.com",
                        "is_active" => "true",
                        "is_email_confirmed" => "true",
                        "role_id" => "10"
                    ],
                    "company" => [
                        "name" => "Cable and Cotton",
                        "country_id" => "78",
                        "is_active" => "true"
                    ],
                    "store" => [
                        "name" => "Cable and Cotton",
                        "url" => "http://cableandcotton.laconicastudio.com",
                        "platform_id" => "1001",
                        "platform_store_id" => "1",
                        "is_rates_enabled" => "true"
                    ]
                ]
            ],
            "redirect_url" => "https://es-staging.easyship.com/magento/registration/903b9e67-ea6b-4173-919a-2550e79a5274/"
        ];
    }

    protected function _isAllowed()
    {
        return true;
    }
}