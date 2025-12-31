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
 * @copyright   Copyright (c) 2022 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Goeasyship\Shipping\Model;

use Exception;
use Goeasyship\Shipping\Api\ShipOrderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Convert\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\OrderRepository;
use Magento\Shipping\Model\ShipmentNotifier;

class ShipOrder implements ShipOrderInterface
{
    public const ORDER_IN_PROGRESS = 'processing';

    /**
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var OrderConfig
     */
    protected $_config;

    /**
     * @var Order
     */
    protected $_convertOrder;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var ShipmentFactory
     */
    protected $_shipmentFactory;

    /**
     * @var OrderRepository
     */
    protected $_orderRepository;

    /**
     * @var ShipmentRepository
     */
    protected $_shipmentRepository;

    /**
     * @var ShipmentNotifier
     */
    protected $_shipmentNotifier;

    /**
     * @var TrackFactory
     */
    protected $_trackFactory;

    /**
     * @var ShipmentExtensionFactory
     */
    protected $_shipmentExtensionFactory;

    /**
     * @param ResourceConnection $resourceConnection
     * @param OrderConfig $config
     * @param Order $convertOrder
     * @param OrderRepository $orderRepository
     * @param ShipmentRepository $shipmentRepository
     * @param ShipmentNotifier $shipmentNotifier
     * @param \Magento\Sales\Model\Order $order
     * @param ShipmentFactory $shipmentFactory
     * @param TrackFactory $trackFactory
     * @param ShipmentExtensionFactory $shipmentExtensionFactory
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        OrderConfig $config,
        Order $convertOrder,
        OrderRepository $orderRepository,
        ShipmentRepository $shipmentRepository,
        ShipmentNotifier $shipmentNotifier,
        \Magento\Sales\Model\Order $order,
        ShipmentFactory $shipmentFactory,
        TrackFactory $trackFactory,
        ShipmentExtensionFactory $shipmentExtensionFactory
    ) {

        $this->_resourceConnection = $resourceConnection;
        $this->_config = $config;
        $this->_convertOrder = $convertOrder;
        $this->_order = $order;
        $this->_shipmentFactory = $shipmentFactory;
        $this->_shipmentRepository = $shipmentRepository;
        $this->_shipmentNotifier = $shipmentNotifier;
        $this->_orderRepository = $orderRepository;
        $this->_trackFactory = $trackFactory;
        $this->_shipmentExtensionFactory = $shipmentExtensionFactory;
    }

    /**
     * Logic of working with shopping
     *
     * @param int|string $orderId
     * @param array $items
     * @param array $trackData
     * @param string $comment
     * @return bool|string
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(
        $orderId,
        $items = [],
        $trackData = [],
        $comment = ''
    ): bool|string {

        $order = $this->_orderRepository->get($orderId);

        if (!$order->canShip()) {
            return false;
        }
        $shipment = $this->_convertOrder->toShipment($order);
        $countItems = count($items);
        foreach ($order->getAllItems() as $orderItem) {
            $this->_addToShip($shipment, $orderItem, $items, $countItems);
        }

        $shipment->getOrder()->setIsInProcess(true);
        $trackData = $this->validateTrackData($trackData);

        if (!empty($trackData)) {
            $track = $this->_trackFactory->create();
            $track->addData($trackData);
            $shipment->addTrack($track);
        }

        if (!empty($comment)) {
            $shipment->addComment($comment);
        }

        $shipment->setShipmentStatus(Shipment::STATUS_NEW);

        // Ensure extension attributes are initialized before setting source code
        $extensionAttributes = $shipment->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->_shipmentExtensionFactory->create();
            $shipment->setExtensionAttributes($extensionAttributes);
        }
        $extensionAttributes->setSourceCode('default');

        $shipment->register();

        $connection = $this->_resourceConnection->getConnection('sales');
        $connection->beginTransaction();
        try {
            $order->setState(self::ORDER_IN_PROGRESS);
            $order->setStatus($this->_config->getStateDefaultStatus($order->getState()));
            $this->_shipmentRepository->save($shipment);
            $this->_orderRepository->save($order);
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw new CouldNotSaveException(
                __('Could not save a shipment, see error log for details' . $e->getMessage())
            );
        }

        return $shipment->toJson();
    }

    /**
     * Add item to shipment
     *
     * @param Shipment $shipment
     * @param Item $orderItem
     * @param array $items
     * @param int $countItems
     * @return bool
     * @throws LocalizedException
     */
    protected function _addToShip($shipment, $orderItem, $items, $countItems): bool
    {
        $needToShip = false;
        $countToShip = null;

        // Check need to ship
        if (is_array($items) && $countItems) {
            // If items array is provided, only ship items that match
            foreach ($items as $item) {
                if (isset($item['item_id']) && ($item['item_id'] == $orderItem->getId())) {
                    $needToShip = true;
                    $countToShip = $item['qty'];
                    break;
                }
            }
        } else {
            // If no items array provided, ship all items
            $needToShip = true;
        }

        if (!$needToShip) {
            return false;
        }

        // Check if order item is virtual or has quantity to ship
        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
            return false;
        }

        $qtyShipped = $countToShip ?? $orderItem->getQtyToShip();

        // Create shipment item with qty
        $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

        // Add shipment item to shipment
        $shipment->addItem($shipmentItem);

        return true;
    }

    /**
     * Validate track data
     *
     * @param array $trackData
     * @return array
     */
    protected function validateTrackData($trackData): array
    {
        if (isset($trackData['tracking_number'])) {
            $trackData['number'] = $trackData['tracking_number'];
            unset($trackData['tracking_number']);
        }

        return $trackData;
    }
}
