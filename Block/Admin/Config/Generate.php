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
 * @copyright   Copyright (c) 2026 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Goeasyship\Shipping\Block\Admin\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Generate extends Field
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Goeasyship_Shipping::system/config/generate.phtml');
    }

    /**
     * Render block
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = '<td class="value">';
        $html .= "<h3>" . __('Here are stores we found in your settings.'
                .' Please select the store to integrate with Easyship.') . "</h3>";
        $html .= $this->getElementHtml($element);
        $html .= '</td>';

            return $this->_decorateRowHtml($element, $html);
    }

    /**
     * Get element html for EasyShip register
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function getElementHtml(AbstractElement $element)
    {
        $id = $element->getStoreid();
        $isActived = $this->_scopeConfig->getValue('easyship_options/ec_shipping/token', 'default', $id);
        $url = $this->getUrl('easyship/easyship/ajaxregister');
        $resetUrl = $this->getUrl('easyship/easyship/resetregister', ['store_id' => $id]);

        $this->addData(
            [
                'store' => $element->getLabel(),
                'element_id' => $element->getHtmlId(),
                'store_id' => $id,
                'actived' => $isActived,
                'store_url' => $url,
                'reset_store_url' => $resetUrl
            ]
        );

        return $this->_toHtml();
    }
}
