<?php
/**
 * Created by PhpStorm.
 * User: mark
 * Date: 16.04.18
 * Time: 13:57
 */

namespace Easyship\Shipping\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeData implements UpgradeDataInterface
{
    protected $_eavSetupFactory;

    public function __construct(\Magento\Eav\Setup\EavSetupFactory $eavSetupFactory) {
        $this->_eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context) {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            $eavSetup = $this->_eavSetupFactory->create(['setup' => $setup]);
            $easyship_height_data = [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Easyship Height (cm)',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE,
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
            ];
            $this->createProductAttribute($eavSetup, 'easyship_height', $easyship_height_data);

            $easyship_width_data = [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Easyship Width (cm)',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE,
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
            ];
            $this->createProductAttribute($eavSetup, 'easyship_width', $easyship_width_data);

            $easyship_length_data = [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Easyship Length (cm)',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE,
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
            ];
            $this->createProductAttribute($eavSetup, 'easyship_length', $easyship_length_data);

            $easyship_category_data = [
                'type' => 'text',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'frontend' => '',
                'label' => 'Easyship Category',
                'input' => 'select',
                'class' => '',
                'source' => 'Easyship\Shipping\Model\Source\Categories',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE,
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
            ];
            $this->createProductAttribute($eavSetup, 'easyship_category', $easyship_category_data);

        }

        $setup->endSetup();
    }

    protected function createProductAttribute($eavSetup, $code, $data)
    {
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, $code, $data);
    }
}