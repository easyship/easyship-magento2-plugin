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

use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Shipment;

class ShipOrder implements \Goeasyship\Shipping\Api\ShipOrderInterface
{
    public const ORDER_IN_PROGRESS = 'processing';

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var OrderConfig
     */
    protected $_config;

    /**
     * @var \Magento\Sales\Model\Convert\Order
     */
    protected $_convertOrder;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $_shipmentFactory;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentRepository
     */
    protected $_shipmentRepository;

    /**
     * @var \Magento\Shipping\Model\ShipmentNotifier
     */
    protected $_shipmentNotifier;

    /**
     * @var Shipment\Track
     */
    protected $_track;

    /**
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param OrderConfig $config
     * @param \Magento\Sales\Model\Convert\Order $convertOrder
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository
     * @param \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory
     * @param Shipment\Track $track
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        OrderConfig $config,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Sales\Model\Order\Shipment\Track $track
    ) {

        $this->_resourceConnection = $resourceConnection;
        $this->_config = $config;
        $this->_convertOrder = $convertOrder;
        $this->_order = $order;
        $this->_shipmentFactory = $shipmentFactory;
        $this->_shipmentRepository = $shipmentRepository;
        $this->_shipmentNotifier = $shipmentNotifier;
        $this->_orderRepository = $orderRepository;
        $this->_track = $track;
    }

    /**
     * Logic of working with shopping
     *
     * @param int|string $orderId
     * @param array $items
     * @param array $trackData
     * @param string $comment
     * @return bool|int|string
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(
        $orderId,
        $items = [],
        $trackData = [],
        $comment = ''
    ) {

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
            $this->_track->addData($trackData);
            $shipment->addTrack($this->_track);
        }

        if (!empty($comment)) {
            $shipment->addComment($comment);
        }

        $shipment->setShipmentStatus(Shipment::STATUS_NEW);

        $shipment->register();

        $connection = $this->_resourceConnection->getConnection('sales');
        $connection->beginTransaction();
        try {
            $order->setState(self::ORDER_IN_PROGRESS);
            $order->setStatus($this->_config->getStateDefaultStatus($order->getState()));
            $this->_shipmentRepository->save($shipment);
            $this->_orderRepository->save($order);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \Magento\Framework\Exception\CouldNotSaveException(
                __('Could not save a shipment, see error log for details')
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
     */
    protected function _addToShip($shipment, $orderItem, $items, $countItems)
    {
        $needToShip = true;
        //Check need to ship
        if (is_array($items) && $countItems) {
            foreach ($items as $item) {
                if (isset($item['item_id']) && ($item['item_id'] == $orderItem->getId())) {
                    $needToShip = true;
                    $countToShip = $item['qty'];
                    break;
                } else {
                    $needToShip = false;
                }
            }
        }

        if (!$needToShip) {
            return false;
        }

        // Check if order item is virtual or has quantity to ship
        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
            return false;
        }

        $qtyShipped = isset($countToShip) ? $countToShip : $orderItem->getQtyToShip();

        // Create shipment item with qty
        $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

        // Add shipment item to shipment
        $shipment->addItem($shipmentItem);
    }

    /**
     * Validate track data
     *
     * @param array $trackData
     * @return mixed
     */
    protected function validateTrackData($trackData)
    {
        if (isset($trackData['tracking_number'])) {
            $trackData['number'] = $trackData['tracking_number'];
            unset($trackData['tracking_number']);
        }

        return $trackData;
    }
}
