<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class TransitionOrder
{
    /**
     * @throws InvalidOrderTransition
     */
    public function handle(Order $order, OrderStatus $to): Order
    {
        return DB::transaction(function () use ($order, $to): Order {
            // The status is read again under the lock: two admins cancelling at once must not return stock twice.
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($to)) {
                throw new InvalidOrderTransition($locked->status, $to);
            }

            if ($to === OrderStatus::Cancelled) {
                $this->returnStock($locked);
            }

            $locked->update(['status' => $to]);
            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }

    private function returnStock(Order $order): void
    {
        $quantities = $order->items()
            ->whereNotNull('product_id')
            ->pluck('quantity', 'product_id');

        // Same lock order as checkout (by id), so a cancel and a checkout cannot deadlock.
        $products = Product::query()
            ->whereKey($quantities->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($products as $product) {
            $product->increment('stock', $quantities[$product->id]);
        }
    }
}
