<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Support\Vat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => OrderStatus::New,
            'customer_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'zip' => fake()->numerify('### ##'),
            'note' => null,
            'vat_rate' => 21,
            'total' => 0,
            'vat_total' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            $order->number ??= $order->created_at->format('Y').str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();
        });
    }

    public function withItem(Product $product, int $quantity = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($product, $quantity) {
            $lineTotal = $product->price * $quantity;

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ]);

            $order->total += $lineTotal;
            $order->vat_total = Vat::fromGross($order->total, $order->vat_rate);
            $order->save();
        });
    }
}
