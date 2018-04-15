<?php

namespace Easyship\Shipping\Api;


interface ShipOrderInterface
{
    /**
     * Creates new Shipment for given Order.
     *
     * @param int $order_id
     * @param mixed $items[]
     * @param mixed $track_data[]
     * @param string $comment
     * @return int Id of created Shipment.
     */
    public function execute(
        $order_id,
        $items = [],
        $track_data = [],
        $comment = ''
    );
}
