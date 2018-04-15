<?php

namespace Easyship\Shipping\Block\Admin\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Connect extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    protected $_consumer;
    protected $_integration;
    protected $_fieldRenderer;
    protected $_storeManager;

    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Magento\Integration\Model\ResourceModel\Oauth\Consumer\Collection $consumer,
        \Magento\Integration\Model\ResourceModel\Integration\Collection $integration,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Generate $generate,
        array $data = []
    )
    {
        parent::__construct($context, $authSession, $jsHelper, $data);

        $this->_consumer = $consumer;
        $this->_integration = $integration;
        $this->_storeManager = $storeManager;
        $this->_fieldRenderer = $generate;
    }

    public function render(AbstractElement $element)
    {
        $integration = $this->_integration
            ->addFieldToFilter('name', 'easyship')
            ->getFirstItem();

        $consumerId = $integration->getConsumerId();
        if (!$consumerId) {
            return false;
        }

        $consumer = $this->_consumer->getItemById($consumerId);
        if (!$consumer->getId()) {
            return false;
        }

        $html = '';

        foreach ($this->_storeManager->getWebsites() as $website) {
            foreach ($website->getGroups() as $webgroup) {
                $stores = $webgroup->getStores();
                foreach ($stores as $store) {
                    $html .= $this->_getFieldHtml($element, $store);
                }
            }
        }

        return $html;

    }

    protected function _getFieldHtml($fieldset, $store)
    {
        $field = $fieldset->addField($store->getId(), 'text',
            array(
                'name'  => 'groups[ec_shipping][fields][store_'.$store->getId().'][value]',
                'label' => $store->getFrontendName(),
                'value' => '',
                'inherit' => true,
                'storeid' => $store->getId()

            ))->setRenderer($this->_fieldRenderer);


        return $field->toHtml();

    }
}