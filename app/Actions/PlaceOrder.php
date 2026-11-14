<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Enums\OrderStatus;
use App\Exceptions\CartChangedException;
use App\Exceptions\EmptyCartException;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Support\Vat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PlaceOrder
{
    /**
     * @throws EmptyCartException
     * @throws CartChangedException when price, availability or stock differ from the cart
     */
    public function handle(Cart $cart, CheckoutData $data): Order
    {
        if ($cart->isEmpty()) {
            throw new EmptyCartException('The cart is empty.');
        }

        $order = DB::transaction(function () use ($cart, $data): Order {
            $items = $cart->items();

            // Locked in id order so concurrent checkouts of overlapping carts cannot deadlock.
            $products = Product::query()
                ->whereKey(array_keys($items))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (! $this->matches($items, $products)) {
                throw new CartChangedException($cart->refresh());
            }

            $total = 0;
            $lines = [];

            foreach ($items as $productId => $item) {
                $product = $products[$productId];
                $product->decrement('stock', $item['quantity']);

                $lineTotal = $item['price'] * $item['quantity'];
                $total += $lineTotal;
                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
            }

            $vatRate = (int) config('shop.vat_rate');

            $order = Order::query()->create([
                'status' => OrderStatus::New,
                'customer_name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'street' => $data->street,
                'city' => $data->city,
                'zip' => $data->zip,
                'note' => $data->note,
                'vat_rate' => $vatRate,
                'total' => $total,
                'vat_total' => Vat::fromGross($total, $vatRate),
            ]);

            $order->items()->createMany($lines);
            $order->update(['number' => $order->created_at->format('Y').str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

            return $order;
        });

        $cart->clear();

        Mail::to($order->email)->queue(new OrderPlaced($order));

        return $order;
    }

    /**
     * @param  array<int, array{quantity: int, price: int}>  $items
     * @param  Collection<int, Product>  $products
     */
    private function matches(array $items, Collection $products): bool
    {
        foreach ($items as $productId => $item) {
            $product = $products->get($productId);

            if ($product === null
                || ! $product->is_available
                || $product->stock < $item['quantity']
                || $product->price !== $item['price']) {
                return false;
            }
        }

        return true;
    }
}
