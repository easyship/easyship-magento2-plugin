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

namespace Easyship\Shipping\Model;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrier;

class EasyshipCarrier extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'easyship';
    protected $_request = null;
    protected $_rawRequest = null;
    protected $_easyshipApi;
    protected $_countryFactory;
    protected $_storeManager;
    protected $storeId = null;

    protected $_rateResultFactory;
    protected $_rateMethodFactory;
    protected $_statusFactory;
    protected $_trackFactory;
    protected $_trackCollectionFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackingResultFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $statusFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory $trackCollectionFactory,
        \Psr\Log\LoggerInterface $logger,
        \Easyship\Shipping\Model\Api\Request $easyshipApi,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_easyshipApi = $easyshipApi;
        $this->_countryFactory = $countryFactory;
        $this->_storeManager = $storeManager;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_statusFactory = $statusFactory;
        $this->_trackFactory = $trackingResultFactory;
        $this->_trackCollectionFactory = $trackCollectionFactory;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }


    public function getAllowedMethods()
    {
        return null;
    }

    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get tracking information
     * @param $tracking
     * @return bool
     */
    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);

        if ($result instanceof \Magento\Shipping\Model\Tracking\Result) {
            $trackings = $result->getAllTrackings();
            if ($trackings) {
                return $trackings[0];
            }
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    /**
     * Get tracking
     * @param $trackings
     * @return mixed
     */
    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings = [$trackings];
        }

        $result = $this->_trackFactory->create();
        $trackings_data = $this->findByNumber($trackings);
        foreach ($trackings as $tracking) {
            $status = $this->_statusFactory->create();
            $status->setCarrier($this->_code);
            $status->setCarrierTitle($this->getConfigData('title'));
            $status->setTracking($tracking);
            $status->setPopup(1);
            if (isset($trackings_data[$tracking]['tracking_page_url'])) {
                $status->setTrackingPageUrl($trackings_data[$tracking]['tracking_page_url']);
            }
            $result->append($status);
        }

        return $result;
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $this->_createEasyShipRequest($request);

        $result = $this->_getQuotes();

        return $result;
    }

    /**
     * Find tracking data by number
     * @param array $trackings
     * @return array
     */
    protected function findByNumber(array $trackings)
    {
        $elements = $this->_trackCollectionFactory->create()->addFieldToFilter('track_number', ['in' => $trackings]);
        $result = [];
        foreach ($elements as $item) {
            $result[$item['track_number']] = $item;
        }
        return $result;
    }

    protected function _createEasyShipRequest(RateRequest $request)
    {
        $this->_request = $request;

        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();

        $r = new \Magento\Framework\DataObject();

        if ($request->getOrigCountry()) {
            $origCountry = $request->getOrigCountry();
        } else {
            $origCountry = $this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $request->getStoreId()
            );
        }

        $r->setData('origin_country_alpha2', $this->_countryFactory->create()->load($origCountry)->getData('iso2_code'));

        if ($request->getOrigPostcode()) {
            $r->setOriginPostalCode($request->getOrigPostcode());
        } else {
            $r->setOriginPostalCode($this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_ZIP,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $request->getStoreId()
            ));
        }

        if ($request->getDestCountryId()) {
            $destCountry = $request->getDestCountryId();
        } else {
            $destCountry = 'US';
        }

        $r->setData('destination_country_alpha2', $this->_countryFactory->create()->load($destCountry)->getData('iso2_code'));

        if ($request->getDestPostcode()) {
            $r->setDestinationPostalCode($request->getDestPostcode());
        }

        $items = [];
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                $itemQty = (int)$item->getQty();
                for ($i = 0; $i < $itemQty; $i++) {
                    $items[] = [
                        'actual_weight' => $this->getWeight($item->getProduct()),
                        'height' => $this->getEasyshipHeight($item->getProduct()),
                        'width' => $this->getEasyshipWidth($item->getProduct()),
                        'length' => $this->getEasyshipLength($item->getProduct()),
                        'category' => $this->getEasyshipCategory($item->getProduct()),
                        'declared_currency' => $currencyCode,
                        'declared_customs_value' => (float)$item->getPrice(),
                        'sku' => $item->getSku()
                    ];
                }
            }
        }

        $r->setItems($items);

        $this->_rawRequest = $r;
    }

    /**
     * Get weight
     * @param $item
     * @return int
     */
    protected function getStoreId()
    {
        if (empty($this->storeId)) {
            $this->storeId = $this->_storeManager->getStore()->getId();
        }

        return $this->storeId;
    }

    /**
     * Get weight
     * @param $item
     * @return int
     */
    protected function getWeight($item)
    {
        if ($item->hasWeight() && !empty($item->getWeight())) {
            return (int)$item->getWeight();
        }

        return 1;
    }

    /**
     * Get easyship category
     * @param $item
     * @return string
     */
    protected function getEasyshipCategory($item)
    {
        if ($item->hasEasyshipCategory() && !empty($item->getEasyshipCategory())) {
            return $item->getEasyshipCategory();
        }

        $base_category = $this->_scopeConfig->getValue(
            'carriers/easyship/base_category',
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_category)) {
            return '';
        }

        return $base_category;
    }

    /**
     * Get easyship height
     * @param $item
     * @return int
     */
    protected function getEasyshipHeight($item)
    {
        if ($item->hasEasyshipHeight() && !empty($item->getEasyshipHeight())) {
            return (int)$item->getEasyshipHeight();
        }

        $base_height = $this->_scopeConfig->getValue(
            'carriers/easyship/base_height',
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_height)) {
            return 0;
        }

        return (int)$base_height;
    }

    /**
     * Get easyship width
     * @param $item
     * @return int
     */
    protected function getEasyshipWidth($item)
    {
        if ($item->hasEasyshipWidth() && !empty($item->getEasyshipWidth())) {
            return (int)$item->getEasyshipWidth();
        }

        $base_width = $this->_scopeConfig->getValue(
            'carriers/easyship/base_width',
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_width)) {
            return 0;
        }

        return (int)$base_width;
    }

    /**
     * Get easyship length
     * @param $item
     * @return int
     */
    protected function getEasyshipLength($item)
    {
        if ($item->hasEasyshipLength() && !empty($item->getEasyshipLength())) {
            return (int)$item->getEasyshipLength();
        }

        $base_length = $this->_scopeConfig->getValue(
            'carriers/easyship/base_length',
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_length)) {
            return 0;
        }

        return (int)$base_length;
    }

    /**
     * @return bool|\Magento\Shipping\Model\Rate\Result
     */
    protected function _getQuotes()
    {
        $rates = $this->_easyshipApi->getQuotes($this->_rawRequest);
        if (empty($rates) || empty($rates['rates'])) {
            return false;
        }

        $prefer_rates = $rates['rates'];

        /**
         * @var \Magento\Shipping\Model\Rate\Result $result
         * @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method
         */
        $result = $this->_rateResultFactory->create();
        foreach ($prefer_rates as $rate) {
            $method = $this->_rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($rate['courier_name']);
            $method->setMethod($rate['short_courier_id']);
            $method->setMethodTitle($rate['full_description']);
            $method->setCost($rate['total_charge']);
            $method->setPrice($rate['total_charge']);
            $result->append($method);
        }

        return $result;
    }
}
