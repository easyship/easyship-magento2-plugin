<?php

namespace Goeasyship\Shipping\Setup\Patch\Data;

use Goeasyship\Shipping\Model\Source\Categories;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Integration\Model\AuthorizationService;
use Magento\Integration\Model\IntegrationFactory;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\OauthService;

class InstallModule implements DataPatchInterface
{
    public const ATTRIBUTES = [
        'easyship_height' => [
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Easyship Height (cm)',
            'input' => 'text',
            'class' => '',
            'source' => '',
            'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => ''
        ],
        'easyship_width' => [
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Easyship Width (cm)',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        ],
        'easyship_length' => [
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Easyship Length (cm)',
            'input' => 'text',
            'class' => '',
            'source' => '',
            'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => ''
        ],
        'easyship_category' => [
            [
                'type' => 'text',
                'backend' => ArrayBackend::class,
                'frontend' => '',
                'label' => 'Easyship Category',
                'input' => 'select',
                'class' => '',
                'source' => Categories::class,
                'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        ]
    ];

    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var IntegrationFactory
     */
    protected $integrationFactory;

    /**
     * @var OauthService
     */
    protected $oauthService;

    /**
     * @var AuthorizationService
     */
    protected $authorizationService;

    /**
     * @var Token
     */
    protected $token;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param IntegrationFactory $integrationFactory
     * @param OauthService $oauthService
     * @param AuthorizationService $authorizationService
     * @param Token $token
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory          $eavSetupFactory,
        IntegrationFactory $integrationFactory,
        OauthService $oauthService,
        AuthorizationService $authorizationService,
        Token $token
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->integrationFactory = $integrationFactory;
        $this->oauthService = $oauthService;
        $this->authorizationService = $authorizationService;
        $this->token = $token;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * Module installation
     *
     * @throws \Zend_Validate_Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply()
    {
        $this->addAttributes();
        $this->createIntegration();
    }

    /**
     * Applying a patch to set desired attributes
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Validate_Exception
     */
    protected function addAttributes()
    {

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (self::ATTRIBUTES as $code => $attribute) {
            if ($eavSetup->getAttribute(Product::ENTITY, $code) === false) {
                $eavSetup->addAttribute(Product::ENTITY, $code, $attribute);
            }
        }
    }

    /**
     * Create Integration for easyship
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Oauth\Exception
     */
    protected function createIntegration()
    {
        $name = 'easyship';

        $integrationExists = $this->integrationFactory->create()->load($name, 'name')->getData();
        if (empty($integrationExists)) {
            $integrationData = [
                'name' => $name,
                'status' => '1',
                'setup_type' => '0'
            ];

            // Code to create Integration
            $integrationFactory = $this->integrationFactory->create();
            $integration = $integrationFactory->setData($integrationData);
            $integration->save();
            $integrationId = $integration->getId();
            $consumerName = 'Integration' . $integrationId;

            // Code to create consumer
            $consumer = $this->oauthService->createConsumer(['name' => $consumerName]);
            $consumerId = $consumer->getId();
            $integration->setConsumerId($consumer->getId());
            $integration->save();

            // Code to grant permission
            $this->authorizationService->grantAllPermissions($integrationId);

            // Code to Activate and Authorize
            $this->token->createVerifierToken($consumerId);
            $this->token->setType('access');
            $this->token->save();
        }
    }
}
