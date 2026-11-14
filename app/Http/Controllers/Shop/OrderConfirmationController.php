<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Reachable only through the signed link from the checkout: order numbers are sequential
 * and would otherwise expose other customers' addresses.
 */
class OrderConfirmationController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('shop.order-confirmation', ['order' => $order->load('items')]);
    }
}
