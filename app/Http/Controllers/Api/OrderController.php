<?php

namespace App\Http\Controllers\Api;

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Exceptions\CartChangedException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderAlreadyPlacedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\RateLimiter;

class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $items = $request->toCartItems();
        $token = $request->cartToken();

        // Before the limit: a retry of an order that already went through must not hit 429.
        $existing = Order::query()->where('cart_token', $token)->first();

        if ($existing !== null) {
            return $this->existing($existing, $items);
        }

        $limiterKey = 'place-order:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('shop.orders_per_hour'))) {
            return response()->json([
                'code' => 'too_many_orders',
                'messages' => ['Z vaší adresy přišlo v poslední hodině příliš mnoho objednávek. Zkuste to prosím později.'],
            ], 429, ['Retry-After' => RateLimiter::availableIn($limiterKey)]);
        }

        $cart = Cart::fromItems(new Store('place-order', new ArraySessionHandler(10)), $items, $token);

        try {
            $order = $placeOrder->handle($cart, $request->toCheckoutData());
        } catch (CartChangedException $e) {
            $corrected = [];
            foreach ($cart->items() as $productId => $item) {
                $corrected[] = ['product_id' => $productId, ...$item];
            }

            return response()->json(['code' => 'cart_changed', 'messages' => $e->messages, 'items' => $corrected], 409);
        } catch (OrderAlreadyPlacedException $e) {
            return $this->existing($e->order, $items);
        } catch (EmptyCartException) {
            // Unreachable: the request requires at least one item.
            abort(422);
        }

        RateLimiter::hit($limiterKey, 3600);

        return (new OrderResource($order->load('items')))->response()->setStatusCode(201);
    }

    /**
     * @param  array<int, array{quantity: int, price: int}>  $items
     */
    private function existing(Order $order, array $items): JsonResponse
    {
        $order->load('items');

        $placed = $order->items->pluck('quantity', 'product_id')->all();
        $sent = array_map(fn (array $item): int => $item['quantity'], $items);

        ksort($placed);
        ksort($sent);

        if ($placed !== $sent) {
            $placedOrder = $order->number !== null ? "objednávka č. {$order->number}" : 'objednávka';

            return response()->json([
                'code' => 'cart_already_ordered',
                'messages' => ["Z tohoto košíku už byla odeslána {$placedOrder}. Košík jsme ponechali – zkontrolujte ho a odešlete objednávku znovu, vznikne nová."],
            ], 409);
        }

        return (new OrderResource($order))->response();
    }
}
