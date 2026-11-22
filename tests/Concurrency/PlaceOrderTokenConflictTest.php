<?php

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Exceptions\OrderAlreadyPlacedException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

// Committed rows would otherwise leak into the next test of this process.
afterEach(fn () => $this->truncateDatabaseTables());

it('returns the order of a parallel request that shares the token but no product', function () {
    Mail::fake();
    // A second connection commits on its own, like a parallel request would.
    config(['database.connections.parallel' => config('database.connections.'.config('database.default'))]);

    $theirs = Product::factory()->create(['stock' => 5]);
    $ours = Product::factory()->create(['stock' => 5]);
    $token = (string) Str::uuid();
    $cart = Cart::fromItems(
        new Store('test', new ArraySessionHandler(10)),
        [$ours->id => ['quantity' => 1, 'price' => $ours->price]],
        $token,
    );

    // Product locks do not queue the two requests: the other one commits after our token check.
    $parallelId = null;
    Order::creating(function () use ($token, $theirs, &$parallelId): void {
        if ($parallelId !== null) {
            return;
        }

        $parallel = DB::connection('parallel');
        $parallelId = $parallel->table('orders')->insertGetId([
            'number' => '2026999999',
            'cart_token' => $token,
            'status' => 'new',
            'customer_name' => 'Jana Nováková',
            'email' => 'jana@example.com',
            'phone' => '+420 000 000 000',
            'street' => 'Dlouhá 12',
            'city' => 'Praha',
            'zip' => '11000',
            'vat_rate' => 21,
            'total' => $theirs->price,
            'vat_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $parallel->table('order_items')->insert([
            'order_id' => $parallelId,
            'product_id' => $theirs->id,
            'product_name' => $theirs->name,
            'unit_price' => $theirs->price,
            'quantity' => 1,
            'line_total' => $theirs->price,
        ]);
    });

    $data = new CheckoutData('Petr Novák', 'petr@example.com', '+420 000 000 001', 'Krátká 1', 'Brno', '60200', null);

    try {
        app(PlaceOrder::class)->handle($cart, $data);
        $caught = null;
    } catch (OrderAlreadyPlacedException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->order->id)->toBe($parallelId)
        ->and(Order::count())->toBe(1)
        ->and($ours->fresh()->stock)->toBe(5)
        ->and($cart->isEmpty())->toBeTrue();
    Mail::assertNothingQueued();
});
