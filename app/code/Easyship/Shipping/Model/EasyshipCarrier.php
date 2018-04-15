<?php

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

    protected $_rateResultFactory;
    protected $_rateMethodFactory;

    public function __construct
    (
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Psr\Log\LoggerInterface $logger,
        \Easyship\Shipping\Model\Api\Request $easyshipApi,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    )
    {
        $this->_easyshipApi = $easyshipApi;
        $this->_countryFactory = $countryFactory;
        $this->_storeManager = $storeManager;
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * No method to specify for Mage_Shipping_Model_Carrier_Interface
     * @return null
     */
    public function getAllowedMethods()
    {
        return null;
    }

    protected function getActivate(RateRequest $request)
    {
        $storeId = $request->getStoreId();
        return $this->_scopeConfig->getValue('easyship_options/ec_shipping/rate_enable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function collectRates(RateRequest $request)
    {
//        if (!$this->getConfigFlag('active') || !$this->getActivate($request)) {
//            return false;
//        }

        $this->_createEasyShipRequest($request);

        $result = $this->_getQuotes();

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
            $origCountry = $this->_scopeConfig->getValue(\Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $request->getStoreId());
        }

        $r->setOriginCountryAlpha2($this->_countryFactory->create()->load($origCountry)->getIso2Code());

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

        $r->setDestinationCountryAlpha2($this->_countryFactory->create()->load($destCountry)->getIso2Code());

        if ( $request->getDestPostcode() ) {
            $r->setDestinationPostalCode( $request->getDestPostcode() );
        }

        $r->setOutputCurrency($currencyCode);

        $items = array();
        if ( $request->getAllItems() ) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem() ) {
                    continue;
                }

                for ($i = 0; $i < $item->getQty(); $i++) {
                    $items[] = array(
                        'actual_weight' =>  $item->getWeight(),
                        'declared_currency' => $currencyCode,
                        'declared_customs_value' =>  (float) $item->getPrice(),
                        'sku' =>  $item->getSku()
                    );
                }
            }
        }

        $r->setItems($items);

        $this->_rawRequest = $r;

    }


    protected function _getQuotes()
    {
        $rates = $this->_easyshipApi->getQuotes($this->_rawRequest);
        if (empty($rates)) {
            return false;
        }

        $prefer_rates = $rates['rates'];
        $this->_logger->info('Prefer Rates: ' . var_export($prefer_rates, 1));

        /**
         * @var \Magento\Shipping\Model\Rate\Result $result
         * @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method
         */
        $result = $this->_rateResultFactory->create();
        foreach ($prefer_rates as $rate) {
            $method = $this->_rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($rate['courier_id']);
            $method->setMethodTitle($rate['full_description']);
            $method->setCost($rate['total_charge']);
            $method->setPrice($rate['total_charge']);
            $result->append($method);
        }

        return $result;
    }
}