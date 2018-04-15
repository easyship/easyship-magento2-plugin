<?php

namespace Easyship\Shipping\Model;

use Magento\Sales\Model\Order\Config as OrderConfig;

class ShipOrder implements \Easyship\Shipping\Api\ShipOrderInterface
{
    const ORDER_IN_PROGRESS = 'processing';

    protected $_resourceConnection;
    protected $_config;

    protected $_convertOrder;
    protected $_order;
    protected $_shipmentFactory;
    protected $_orderRepository;
    protected $_shipmentRepository;
    protected $_shipmentNotifier;
    protected $_track;

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
    )
    {
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

    public function execute(
        $orderId,
        $items = [],
        $trackData = [],
        $comment = ''
    )
    {
        //$order = $this->_order->getItemById($orderId);
        $order = $this->_orderRepository->get($orderId);

        if (!$order->canShip()) {
            return false;
        }
        $shipment = $this->_convertOrder->toShipment($order);
        foreach ($order->getAllItems() as $orderItem) {
            $needToShip = true;
            //Check need to ship
            if (is_array($items) && count($items)) {
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
                continue;
            }

            // Check if order item is virtual or has quantity to ship
            if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qtyShipped = isset($countToShip) ? $countToShip : $orderItem->getQtyToShip();

            // Create shipment item with qty
            $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

            // Add shipment item to shipment
            $shipment->addItem($shipmentItem);
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

        $shipment->setShipmentStatus(\Magento\Sales\Model\Order\Shipment::STATUS_NEW);

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

    protected function validateTrackData($trackData)
    {
        if (isset($trackData['tracking_number'])) {
            $trackData['number'] = $trackData['tracking_number'];
            unset($trackData['tracking_number']);
        }

        return $trackData;
    }
}
