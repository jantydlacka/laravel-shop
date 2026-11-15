<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Enums\OrderStatus;
use App\Exceptions\CartChangedException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderAlreadyPlacedException;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Support\Vat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PlaceOrder
{
    /**
     * @param  string|null  $seenFingerprint  the cart the customer confirmed; null skips the check
     *
     * @throws EmptyCartException
     * @throws CartChangedException when price, availability or stock differ from the cart
     * @throws OrderAlreadyPlacedException when another request already placed this cart
     */
    public function handle(Cart $cart, CheckoutData $data, ?string $seenFingerprint = null): Order
    {
        if ($cart->isEmpty()) {
            throw new EmptyCartException('The cart is empty.');
        }

        if ($seenFingerprint !== null && $seenFingerprint !== $cart->fingerprint()) {
            throw new CartChangedException(['Košík se mezitím změnil, třeba v jiném okně. Zkontrolujte souhrn a objednávku odešlete znovu.']);
        }

        $token = $cart->token();

        try {
            $order = $this->create($cart, $data, $token);
        } catch (UniqueConstraintViolationException) {
            // The session is not locked: a parallel request with the same cart got there first.
            $cart->clear();

            throw new OrderAlreadyPlacedException(Order::query()->where('cart_token', $token)->sole());
        }

        $cart->clear();

        Mail::to($order->email)->queue(new OrderPlaced($order));

        return $order;
    }

    private function create(Cart $cart, CheckoutData $data, string $token): Order
    {
        return DB::transaction(function () use ($cart, $data, $token): Order {
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
                'cart_token' => $token,
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
