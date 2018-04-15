<?php

namespace Easyship\Shipping\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    protected $_integrationFactory;
    protected $_oauthService;
    protected $_authorizationService;
    protected $_token;

    public function __construct
    (
        \Magento\Integration\Model\IntegrationFactory $integrationFactory,
        \Magento\Integration\Model\OauthService $oauthService,
        \Magento\Integration\Model\AuthorizationService $authorizationService,
        \Magento\Integration\Model\Oauth\Token $token
    )
    {
        $this->_integrationFactory = $integrationFactory;
        $this->_oauthService = $oauthService;
        $this->_authorizationService = $authorizationService;
        $this->_token = $token;
    }

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $update = $setup;

        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $table = $update->getTable('sales_shipment_track');
            $update->getConnection()->addColumn(
                $table,
                'tracking_page_url',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'size' => 255,
                    'nullable' => false,
                    'comment' => 'Tracking page url'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $this->createIntegration();
        }

        $setup->endSetup();
    }

    protected function createIntegration()
    {
        $name = 'easyship';
        $email = 'test@gmail.com';

        $integrationExists = $this->_integrationFactory->create()->load($name, 'name')->getData();
        if (empty($integrationExists)) {
            $integrationData = array(
                'name' => $name,
                'email' => $email,
                'status' => '1',
                'setup_type' => '0'
            );

            // Code to create Integration
            $integrationFactory = $this->_integrationFactory->create();
            $integration = $integrationFactory->setData($integrationData);
            $integration->save();
            $integrationId = $integration->getId();
            $consumerName = 'Integration' . $integrationId;


            // Code to create consumer
            $consumer = $this->_oauthService->createConsumer(['name' => $consumerName]);
            $consumerId = $consumer->getId();
            $integration->setConsumerId($consumer->getId());
            $integration->save();


            // Code to grant permission
            $this->_authorizationService->grantAllPermissions($integrationId);


            // Code to Activate and Authorize
            $this->_token->createVerifierToken($consumerId);
            $this->_token->setType('access');
            $this->_token->save();
        }
    }

}