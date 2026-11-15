<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Another request already turned this cart into an order.
 */
class OrderAlreadyPlacedException extends RuntimeException
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct("The cart was already placed as order {$order->number}.");
    }
}
