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

namespace Easyship\Shipping\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    protected $_integrationFactory;
    protected $_oauthService;
    protected $_authorizationService;
    protected $_token;

    public function __construct(
        \Magento\Integration\Model\IntegrationFactory $integrationFactory,
        \Magento\Integration\Model\OauthService $oauthService,
        \Magento\Integration\Model\AuthorizationService $authorizationService,
        \Magento\Integration\Model\Oauth\Token $token
    ) {

        $this->_integrationFactory = $integrationFactory;
        $this->_oauthService = $oauthService;
        $this->_authorizationService = $authorizationService;
        $this->_token = $token;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        $table = $installer->getTable('sales_shipment_track');
        $installer->getConnection()->addColumn(
            $table,
            'tracking_page_url',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'size' => 255,
                'nullable' => false,
                'comment' => 'Tracking page url'
            ]
        );

        $this->createIntegration();


        $installer->endSetup();
    }

    protected function createIntegration()
    {
        $name = 'easyship';

        $integrationExists = $this->_integrationFactory->create()->load($name, 'name')->getData();
        if (empty($integrationExists)) {
            $integrationData = [
                'name' => $name,
                'status' => '1',
                'setup_type' => '0'
            ];

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
