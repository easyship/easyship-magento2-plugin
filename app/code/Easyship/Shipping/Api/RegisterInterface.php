<?php

namespace Easyship\Shipping\Api;

interface RegisterInterface
{
    /**
     * @param string $store_id
     * @param string $token
     * @return mixed
     */
    public function saveToken($store_id, $token);
}