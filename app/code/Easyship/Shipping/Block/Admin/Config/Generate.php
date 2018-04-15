<?php

namespace Easyship\Shipping\Block\Admin\Config;

class Generate extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Easyship_Shipping::system/config/generate.phtml';

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<td class="value">';
        $html .= "<h3>" . __('Here are stores we found in your settings. Please select the store to integrate with Easyship.') . "</h3>";
        $html .= $this->_getElementHtml($element);
        $html .= '</td>';

        return $this->_decorateRowHtml($element, $html);
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
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