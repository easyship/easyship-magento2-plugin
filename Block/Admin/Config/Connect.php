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

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Helper\Js;
use Magento\Integration\Model\ResourceModel\Oauth\Consumer\Collection;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class Connect extends Fieldset
{
    /**
     * @var Collection
     */
    protected $consumer;

    /**
     * @var \Magento\Integration\Model\ResourceModel\Integration\Collection
     */
    protected $integration;

    /**
     * @var Generate
     */
    protected $fieldRenderer;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param Session $authSession
     * @param Js $jsHelper
     * @param Collection $consumer
     * @param \Magento\Integration\Model\ResourceModel\Integration\Collection $integration
     * @param StoreManagerInterface $storeManager
     * @param Generate $generate
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $authSession,
        Js $jsHelper,
        Collection $consumer,
        \Magento\Integration\Model\ResourceModel\Integration\Collection $integration,
        StoreManagerInterface $storeManager,
        Generate $generate,
        array $data = []
    ) {

        parent::__construct($context, $authSession, $jsHelper, $data);

        $this->consumer = $consumer;
        $this->integration = $integration;
        $this->storeManager = $storeManager;
        $this->fieldRenderer = $generate;
    }

    /**
     * Render block
     *
     * @param AbstractElement $element
     * @return false|string
     * @throws NoSuchEntityException
     */
    public function render(AbstractElement $element)
    {
        $integration = $this->integration
            ->addFieldToFilter('name', 'easyship')
            ->setPageSize(1)
            ->setCurPage(1)
            ->getLastItem();

        $consumerId = $integration->getConsumerId();
        if (!$consumerId) {
            return false;
        }

        $consumer = $this->consumer->getItemById($consumerId);
        if (!$consumer->getId()) {
            return false;
        }

        $html = '';

        foreach ($this->storeManager->getWebsites() as $website) {
            foreach ($website->getGroups() as $webgroup) {
                $stores = $webgroup->getStores();
                foreach ($stores as $store) {
                    $html .= $this->_getFieldHtml($element, $store);
                }
            }
        }

        return $html;
    }

    /**
     * Render html for field
     *
     * @param AbstractElement $fieldset
     * @param Store $store
     * @return mixed
     * @throws NoSuchEntityException
     */
    protected function _getFieldHtml($fieldset, $store)
    {
        $field = $fieldset->addField(
            $store->getId(),
            'text',
            [
                'name'  => 'groups[ec_shipping][fields][store_'.$store->getId().'][value]',
                'label' => $store->getFrontendName(),
                'value' => '',
                'inherit' => true,
                'storeid' => $store->getId()

            ]
        )->setRenderer($this->fieldRenderer);

        return $field->toHtml();
    }
}
