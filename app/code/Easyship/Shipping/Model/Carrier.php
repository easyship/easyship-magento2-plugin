<?php

namespace Easyship\Shipping\Model;

use Magento\Framework\Xml\Security;
use Magento\Ups\Helper\Config;

class Carrier extends \Magento\Ups\Model\Carrier
{
    protected $_trackCollectionFactory;

    public function __construct
    (
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory $trackCollectionFactory,
        Config $configHelper,
        array $data = [])
    {
        $this->_trackCollectionFactory = $trackCollectionFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $xmlSecurity, $xmlElFactory, $rateFactory, $rateMethodFactory, $trackFactory, $trackErrorFactory, $trackStatusFactory, $regionFactory, $countryFactory, $currencyFactory, $directoryData, $stockRegistry, $localeFormat, $configHelper, $data);
    }

    protected function findByNumber(array $trackings)
    {
        $elements = $this->_trackCollectionFactory->create()->addFieldToFilter('track_number',['in' => $trackings]);
        $result = [];
        foreach ($elements as $item) {
            $result[$item['track_number']] = $item;
        }
        return $result;
    }

    protected function _getCgiTracking($trackings)
    {
        $result = $this->_trackFactory->create();
        $trackings_data = $this->findByNumber($trackings);
        foreach ($trackings as $tracking) {
            $status = $this->_trackStatusFactory->create();
            $status->setCarrier('ups');
            $status->setCarrierTitle($this->getConfigData('title'));
            $status->setTracking($tracking);
            $status->setPopup(1);
            $status->setUrl(
                "http://wwwapps.ups.com/WebTracking/processInputRequest?HTMLVersion=5.0&error_carried=true" .
                "&tracknums_displayed=5&TypeOfInquiryNumber=T&loc=en_US&InquiryNumber1={$tracking}" .
                "&AgreeToTermsAndConditions=yes"
            );
            if (isset($trackings_data[$tracking]['tracking_page_url'])) {
                $status->setTrackingPageUrl($trackings_data[$tracking]['tracking_page_url']);
            }
            $result->append($status);
        }

        $this->_result = $result;

        return $result;
    }
}