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
 * @copyright   Copyright (c) 2018 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

namespace Goeasyship\Shipping\Api;

interface ShipOrderInterface
{
    /**
     * Creates new Shipment for given Order.
     *
     * @param int $order_id
     * @param mixed $items []
     * @param mixed $track_data []
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
