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

namespace Easyship\Shipping\Model\Source;

class Categories extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    protected $_options;

    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = $this->getBaseCategories();
        }
        return $this->_options;
    }

    /**
     * Return base easyship categories
     * @see https://developers.easyship.com/reference#request-rates-and-taxes
     * @return array
     */
    protected function getBaseCategories()
    {
        return [
            ['value' => 'mobiles', 'label' => __('Mobiles')],
            ['value' => 'tablets', 'label' => __('Tablets')],
            ['value' => 'computers_laptops', 'label' => __('Computers and laptops')],
            ['value' => 'cameras', 'label' => __('Cameras')],
            ['value' => 'accessory_no_battery', 'label' => __('Accessory without battery')],
            ['value' => 'accessory_battery', 'label' => __('Accessory with battery')],
            ['value' => 'health_beauty', 'label' => __('Health & Beauty')],
            ['value' => 'fashion', 'label' => __('Fashion')],
            ['value' => 'watches', 'label' => __('Watches')],
            ['value' => 'home_appliances', 'label' => __('Home appliances')],
            ['value' => 'home_decor', 'label' => __('Home decor')],
            ['value' => 'toys', 'label' => __('Toys')],
            ['value' => 'sport', 'label' => __('Sport')],
            ['value' => 'luggage', 'label' => __('Luggage')],
            ['value' => 'audio_video', 'label' => __('Audio & Video')],
            ['value' => 'documents', 'label' => __('Documents')],
            ['value' => 'jewelry', 'label' => __('Jewelry')],
            ['value' => 'dry_food_supplements', 'label' => __('Dry food supplements')],
            ['value' => 'books_collectionables', 'label' => __('Books')],
            ['value' => 'pet_accessory', 'label' => __('Pet accessory')],
            ['value' => 'gaming', 'label' => __('Gaming')]
        ];
    }
}
