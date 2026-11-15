<?php

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Enums\OrderStatus;
use App\Exceptions\CartChangedException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderAlreadyPlacedException;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Mail;

covers(PlaceOrder::class);

beforeEach(function () {
    Mail::fake();
});

function checkoutData(): CheckoutData
{
    return new CheckoutData(
        name: 'Jana Nováková',
        email: 'jana@example.com',
        phone: '+420 777 123 456',
        street: 'Dlouhá 12',
        city: 'Praha',
        zip: '110 00',
        note: 'Zvonit dvakrát',
    );
}

function placeOrder(): Order
{
    return app(PlaceOrder::class)->handle(app(Cart::class), checkoutData());
}

it('places an order with a snapshot of names and prices', function () {
    $a = Product::factory()->create(['name' => 'Moka konvička', 'price' => 89900, 'stock' => 5]);
    $b = Product::factory()->create(['name' => 'Earl Grey', 'price' => 14900, 'stock' => 2]);
    app(Cart::class)->add($a, 2);
    app(Cart::class)->add($b, 1);

    $order = placeOrder();

    expect($order->status)->toBe(OrderStatus::New)
        ->and($order->number)->toMatch('/^'.now()->format('Y').'\d{6}$/')
        ->and($order->number)->toBe(now()->format('Y').str_pad((string) $order->id, 6, '0', STR_PAD_LEFT))
        ->and($order->total)->toBe(194700)
        ->and($order->vat_rate)->toBe(21)
        ->and($order->vat_total)->toBe(33791)
        ->and($order->customer_name)->toBe('Jana Nováková')
        ->and($order->email)->toBe('jana@example.com')
        ->and($order->zip)->toBe('110 00')
        ->and($order->note)->toBe('Zvonit dvakrát');

    expect($order->items->map->only(['product_id', 'product_name', 'unit_price', 'quantity', 'line_total'])->all())->toBe([
        ['product_id' => $a->id, 'product_name' => 'Moka konvička', 'unit_price' => 89900, 'quantity' => 2, 'line_total' => 179800],
        ['product_id' => $b->id, 'product_name' => 'Earl Grey', 'unit_price' => 14900, 'quantity' => 1, 'line_total' => 14900],
    ]);
});

it('numbers an order by the Czech calendar year', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:30', 'UTC'));
    app(Cart::class)->add(Product::factory()->create(['stock' => 1]), 1);

    expect(placeOrder()->number)->toStartWith('2027');
});

it('stores order totals beyond the 32-bit range', function () {
    $products = Product::factory()->count(3)->create(['price' => 100_000_000, 'stock' => 99]);
    $products->each(fn (Product $product) => app(Cart::class)->add($product, 99));

    $order = placeOrder()->fresh();

    expect($order->total)->toBe(29_700_000_000)
        ->and($order->items->first()->line_total)->toBe(9_900_000_000);
});

it('decrements stock', function () {
    $product = Product::factory()->create(['stock' => 5]);
    app(Cart::class)->add($product, 2);

    placeOrder();

    expect($product->fresh()->stock)->toBe(3);
});

it('allows ordering the last piece', function () {
    $product = Product::factory()->create(['stock' => 1]);
    app(Cart::class)->add($product, 1);

    placeOrder();

    expect($product->fresh()->stock)->toBe(0);
});

it('empties the cart', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    placeOrder();

    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

it('queues the confirmation mail to the customer', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    $order = placeOrder();

    Mail::assertQueued(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->hasTo('jana@example.com') && $mail->order->is($order));
});

it('stores the VAT rate from the configuration', function () {
    config(['shop.vat_rate' => 12]);
    app(Cart::class)->add(Product::factory()->create(['price' => 11200, 'stock' => 5]), 1);

    $order = placeOrder();

    expect($order->vat_rate)->toBe(12)->and($order->vat_total)->toBe(1200);
});

it('keeps the stored prices when the product price changes later', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $order = placeOrder();

    $product->update(['price' => 99900, 'name' => 'Přejmenováno']);

    $item = $order->fresh()->items->sole();
    expect($item->unit_price)->toBe(10000)
        ->and($item->product_name)->not->toBe('Přejmenováno')
        ->and($order->fresh()->total)->toBe(10000);
});

it('refuses an empty cart', function () {
    placeOrder();
})->throws(EmptyCartException::class);

it('does not create a second order on a repeated submit', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);
    placeOrder();

    expect(fn () => placeOrder())->toThrow(EmptyCartException::class)
        ->and(Order::count())->toBe(1);
});

dataset('stale carts', [
    'price changed' => [fn (Product $p) => $p->update(['price' => 12000]), 'cena'],
    'made unavailable' => [fn (Product $p) => $p->update(['is_available' => false]), 'nabídce'],
    'stock dropped below quantity' => [fn (Product $p) => $p->update(['stock' => 1]), 'skladem'],
    'sold out' => [fn (Product $p) => $p->update(['stock' => 0]), 'vyprodaný'],
    'deleted' => [fn (Product $p) => $p->delete(), 'nabídce'],
]);

it('refuses a stale cart without creating an order or touching stock', function (Closure $change, string $hint) {
    $stable = Product::factory()->create(['price' => 5000, 'stock' => 5]);
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 5]);
    app(Cart::class)->add($stable, 1);
    app(Cart::class)->add($product, 2);
    $change($product);
    $stockBefore = Product::query()->pluck('stock', 'id')->all();

    try {
        placeOrder();
        $this->fail('CartChangedException was not thrown');
    } catch (CartChangedException $e) {
        expect(implode(' ', $e->messages))->toContain($hint);
    }

    expect(Order::count())->toBe(0)
        ->and(Product::query()->pluck('stock', 'id')->all())->toBe($stockBefore)
        ->and(app(Cart::class)->quantityOf($stable->id))->toBe(1)
        ->and(app(Cart::class)->refresh())->toBe([]);
    Mail::assertNothingQueued();
})->with('stale carts');

it('updates the cart to the new price when refusing it', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $product->update(['price' => 12000]);

    expect(fn () => placeOrder())->toThrow(CartChangedException::class)
        ->and(app(Cart::class)->total())->toBe(12000);
});

it('places the order after the customer saw the refreshed cart', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $product->update(['price' => 12000]);
    rescue(fn () => placeOrder(), report: false);

    $order = placeOrder();

    expect($order->total)->toBe(12000);
});

it('places one order when two requests submit the same cart at once', function () {
    $product = Product::factory()->create(['stock' => 5]);
    $first = new Store('first', new ArraySessionHandler(10));
    (new Cart($first))->add($product, 2);
    // A second request read the session before the first one cleared the cart.
    $second = new Store('second', new ArraySessionHandler(10));
    $second->replace($first->all());

    $order = app(PlaceOrder::class)->handle(new Cart($first), checkoutData());

    try {
        app(PlaceOrder::class)->handle(new Cart($second), checkoutData());
        $this->fail('OrderAlreadyPlacedException was not thrown');
    } catch (OrderAlreadyPlacedException $e) {
        expect($e->order->is($order))->toBeTrue();
    }

    expect(Order::count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(3)
        ->and((new Cart($second))->isEmpty())->toBeTrue();
    Mail::assertQueuedCount(1);
});

it('refuses a cart that changed since the customer saw the summary', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    $cart = app(Cart::class);
    $cart->add($product, 1);
    $seen = $cart->fingerprint();
    $cart->add($product, 5);

    expect(fn () => app(PlaceOrder::class)->handle($cart, checkoutData(), $seen))
        ->toThrow(CartChangedException::class, 'Košík se mezitím změnil')
        ->and(Order::count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(10);

    expect(app(PlaceOrder::class)->handle($cart, checkoutData(), $cart->fingerprint())->total)->toBe(60000);
});
