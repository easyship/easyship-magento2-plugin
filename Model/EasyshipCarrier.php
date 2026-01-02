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

namespace Goeasyship\Shipping\Model;

use Goeasyship\Shipping\Model\Api\Request;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class EasyshipCarrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'easyship';

    /**
     * @var ?RateRequest
     */
    protected $_request = null;

    /**
     * @var ?DataObject
     */
    protected $_rawRequest = null;

    /**
     * @var Api\Request
     */
    protected $_easyshipApi;

    /**
     * @var CountryFactory
     */
    protected $_countryFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ?int
     */
    protected $storeId = null;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var StatusFactory
     */
    protected $_statusFactory;

    /**
     * @var ResultFactory
     */
    protected $_trackFactory;

    /**
     * @var CollectionFactory
     */
    protected $_trackCollectionFactory;

    /**
     * @var Escaper
     */
    protected $_escaper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param ResultFactory $trackingResultFactory
     * @param StatusFactory $statusFactory
     * @param MethodFactory $rateMethodFactory
     * @param CollectionFactory $trackCollectionFactory
     * @param LoggerInterface $logger
     * @param Api\Request $easyshipApi
     * @param CountryFactory $countryFactory
     * @param StoreManagerInterface $storeManager
     * @param Escaper $_escaper
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        ResultFactory $trackingResultFactory,
        StatusFactory $statusFactory,
        MethodFactory $rateMethodFactory,
        CollectionFactory $trackCollectionFactory,
        LoggerInterface $logger,
        Request $easyshipApi,
        CountryFactory $countryFactory,
        StoreManagerInterface $storeManager,
        Escaper $_escaper,
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
        $this->_escaper = $_escaper;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Allowed methods
     *
     * @return null
     */
    public function getAllowedMethods()
    {
        return null;
    }

    /**
     * Tracking available
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Get tracking information
     *
     * @param string $tracking
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
     *
     * @param string $trackings
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

    /**
     * Collect rates
     *
     * @param RateRequest $request
     * @return bool|Result
     * @throws NoSuchEntityException
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $this->_createEasyShipRequest($request);

        return $this->_getQuotes();
    }

    /**
     * Find tracking data by number
     *
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

    /**
     * Logic for create easyship request
     *
     * @param RateRequest $request
     * @return void
     * @throws NoSuchEntityException
     */
    protected function _createEasyShipRequest(RateRequest $request)
    {
        $this->_request = $request;

        $r = new DataObject();

        if ($request->getOrigCountry()) {
            $origCountry = $request->getOrigCountry();
        } else {
            $origCountry = $this->_scopeConfig->getValue(
                Shipment::XML_PATH_STORE_COUNTRY_ID,
                ScopeInterface::SCOPE_STORE,
                $request->getStoreId()
            );
        }

        $r->setData(
            'origin_country_alpha2',
            $this->_countryFactory->create()->load($origCountry)->getData('iso2_code')
        );

        if ($request->getOrigPostcode()) {
            $r->setOriginPostalCode($request->getOrigPostcode());
        } else {
            $r->setOriginPostalCode($this->_scopeConfig->getValue(
                Shipment::XML_PATH_STORE_ZIP,
                ScopeInterface::SCOPE_STORE,
                $request->getStoreId()
            ));
        }

        if ($request->getDestCountryId()) {
            $destCountry = $request->getDestCountryId();
        } else {
            $destCountry = 'US';
        }

        $r->setData(
            'destination_country_alpha2',
            $this->_countryFactory->create()->load($destCountry)->getData('iso2_code')
        );

        if ($request->getDestPostcode()) {
            $r->setDestinationPostalCode($request->getDestPostcode());
        }

        $this->setAddressToRequest($r, $request);

        $currentCurrencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $r->setData('output_currency', $currentCurrencyCode);

        $items = [];
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                $itemQty = (int)$item->getQty();
                $unitPriceInclTaxAndDiscount =
                    ($item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount()) / $itemQty;
                for ($i = 0; $i < $itemQty; $i++) {
                    $items[] = [
                        'actual_weight' => $this->getWeight($item->getProduct()),
                        'height' => $this->getEasyshipHeight($item->getProduct()),
                        'width' => $this->getEasyshipWidth($item->getProduct()),
                        'length' => $this->getEasyshipLength($item->getProduct()),
                        'category' => $this->getEasyshipCategory($item->getProduct()),
                        'declared_currency' => $currentCurrencyCode,
                        'declared_customs_value' => $unitPriceInclTaxAndDiscount,
                        'sku' => $item->getSku(),
                    ];
                }
            }
        }

        $r->setItems($items);

        $this->_rawRequest = $r;
    }

    /**
     * Get Store Id
     *
     * @return int
     * @throws NoSuchEntityException
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
     *
     * @param AbstractItem $item
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
     *
     * @param AbstractItem $item
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getEasyshipCategory($item)
    {
        if ($item->hasEasyshipCategory() && !empty($item->getEasyshipCategory())) {
            return $item->getEasyshipCategory();
        }

        $base_category = $this->_scopeConfig->getValue(
            'carriers/easyship/base_category',
            ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_category)) {
            return '';
        }

        return $base_category;
    }

    /**
     * Get easyship height
     *
     * @param AbstractItem $item
     * @return int
     * @throws NoSuchEntityException
     */
    protected function getEasyshipHeight($item)
    {
        if ($item->hasEasyshipHeight() && !empty($item->getEasyshipHeight())) {
            return (int)$item->getEasyshipHeight();
        }

        $base_height = $this->_scopeConfig->getValue(
            'carriers/easyship/base_height',
            ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_height)) {
            return 0;
        }

        return (int)$base_height;
    }

    /**
     * Get easyship width
     *
     * @param AbstractItem $item
     * @return int
     * @throws NoSuchEntityException
     */
    protected function getEasyshipWidth($item)
    {
        if ($item->hasEasyshipWidth() && !empty($item->getEasyshipWidth())) {
            return (int)$item->getEasyshipWidth();
        }

        $base_width = $this->_scopeConfig->getValue(
            'carriers/easyship/base_width',
            ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_width)) {
            return 0;
        }

        return (int)$base_width;
    }

    /**
     * Get easyship length
     *
     * @param AbstractItem $item
     * @return int
     * @throws NoSuchEntityException
     */
    protected function getEasyshipLength($item)
    {
        if ($item->hasEasyshipLength() && !empty($item->getEasyshipLength())) {
            return (int)$item->getEasyshipLength();
        }

        $base_length = $this->_scopeConfig->getValue(
            'carriers/easyship/base_length',
            ScopeInterface::SCOPE_WEBSITE,
            $this->getStoreId()
        );

        if (empty($base_length)) {
            return 0;
        }

        return (int)$base_length;
    }

    /**
     * Logic for get Quotes
     *
     * @return bool|Result
     * @throws NoSuchEntityException
     */
    protected function _getQuotes()
    {
        $rates = $this->_easyshipApi->getQuotes($this->_rawRequest);
        if (empty($rates) || empty($rates['rates'])) {
            return false;
        }

        $prefer_rates = $rates['rates'];

        /**
         * @var Result $result
         * @var Method $method
         */
        $currentCurrencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $baseCurrency = $this->_storeManager->getStore()->getBaseCurrency();
        $convertRate = $baseCurrency->getRate($currentCurrencyCode);

        $result = $this->_rateResultFactory->create();
        foreach ($prefer_rates as $rate) {
            $convertedPrice = $rate['total_charge'] / $convertRate;
            $method = $this->_rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($rate['courier_name']);
            $method->setMethod($rate['short_courier_id']);
            $method->setMethodTitle($rate['full_description']);
            $method->setCost($convertedPrice);
            $method->setPrice($convertedPrice);
            $result->append($method);
        }

        return $result;
    }

    /**
     * Set address to request
     *
     * @param DataObject $data
     * @param RateRequest $request
     */
    protected function setAddressToRequest($data, $request)
    {
        if ($request->getDestCity()) {
            $data->setDestinationCity(
                $this->_escaper->escapeHtml($request->getDestCity(), [ENT_XML1])
            );
        }

        if ($request->getDestRegionCode()) {
            $data->setDestinationState($request->getDestRegionCode());
        }

        $address = explode("\n", $request->getDestStreet());
        if (!empty($address[0])) {
            $data->setData('destination_address_line_1', $address[0]);
        } else {
            $data->setData('destination_address_line_1', '');
        }

        if (!empty($address[1])) {
            $data->setData('destination_address_line_2', $address[1]);
        } else {
            $data->setData('destination_address_line_2', '');
        }
    }
}
