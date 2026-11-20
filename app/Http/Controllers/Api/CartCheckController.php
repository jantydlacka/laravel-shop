<?php

namespace App\Http\Controllers\Api;

use App\Cart\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CartItemsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

class CartCheckController extends Controller
{
    public function __invoke(CartItemsRequest $request): JsonResponse
    {
        $cart = Cart::fromItems(
            new Store('cart-check', new ArraySessionHandler(10)),
            $request->toCartItems(),
            'check',
        );

        $messages = $cart->refresh();

        $items = [];
        foreach ($cart->items() as $productId => $item) {
            $items[] = ['product_id' => $productId, ...$item];
        }

        return response()->json(['items' => $items, 'messages' => $messages]);
    }
}
