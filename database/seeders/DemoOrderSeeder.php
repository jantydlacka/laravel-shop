<?php

namespace Database\Seeders;

use App\Actions\PlaceOrder;
use App\Actions\TransitionOrder;
use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

/**
 * Places the demo orders through the real checkout and status changes, so stock and totals add up.
 * Runs only on a database without orders – a second run would take the goods off the stock again.
 */
class DemoOrderSeeder extends Seeder
{
    /**
     * @var list<array{customer: array{string, string, string, string, string, string}, items: array<string, int>, path: list<OrderStatus>, days_ago: int}>
     */
    private const ORDERS = [
        [
            'customer' => ['Jana Nováková', 'jana.novakova@example.com', '000 000 001', 'Na Příkopě 12', 'Praha', '110 00'],
            'items' => ['espresso-blend' => 1, 'porcelanovy-salek' => 2],
            'path' => [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed],
            'days_ago' => 9,
        ],
        [
            'customer' => ['Petr Svoboda', 'petr.svoboda@example.com', '000 000 002', 'Masarykova 5', 'Brno', '602 00'],
            'items' => ['moka-konvicka' => 1, 'kolumbie-supremo' => 2],
            'path' => [OrderStatus::Processing, OrderStatus::Shipped],
            'days_ago' => 6,
        ],
        [
            'customer' => ['Lucie Dvořáková', 'lucie.dvorakova@example.com', '000 000 003', 'Hlavní 88', 'Ostrava', '702 00'],
            'items' => ['rucni-mlynek' => 1],
            'path' => [OrderStatus::Cancelled],
            'days_ago' => 5,
        ],
        [
            'customer' => ['Tomáš Černý', 'tomas.cerny@example.com', '000 000 004', 'Jungmannova 3', 'Plzeň', '301 00'],
            'items' => ['sencha-zeleny-caj' => 2, 'earl-grey' => 1, 'rooibos-vanilka' => 1],
            'path' => [OrderStatus::Processing],
            'days_ago' => 2,
        ],
        [
            'customer' => ['Eva Procházková', 'eva.prochazkova@example.com', '000 000 005', 'Palackého 21', 'Olomouc', '779 00'],
            'items' => ['french-press' => 1, 'espresso-blend' => 1],
            'path' => [],
            'days_ago' => 1,
        ],
        [
            'customer' => ['Martin Kučera', 'martin.kucera@example.com', '000 000 006', 'Komenského 7', 'Liberec', '460 01'],
            'items' => ['earl-grey' => 3],
            'path' => [],
            'days_ago' => 0,
        ],
    ];

    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly TransitionOrder $transitionOrder,
    ) {}

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        foreach (self::ORDERS as $demo) {
            $cart = new Cart(new Store('demo', new ArraySessionHandler(1)));

            foreach ($demo['items'] as $slug => $quantity) {
                $cart->add(Product::query()->where('slug', $slug)->firstOrFail(), $quantity);
            }

            $order = $this->placeOrder->handle($cart, new CheckoutData(...$demo['customer'], note: null));

            foreach ($demo['path'] as $status) {
                $this->transitionOrder->handle($order, $status);
            }

            $placedAt = now()->subDays($demo['days_ago'])->subMinutes(25 * count($demo['items']));
            $order->forceFill(['created_at' => $placedAt])->saveQuietly();
        }
    }
}
