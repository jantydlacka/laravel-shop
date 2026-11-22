<?php

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Cart\CartLine;
use App\Data\CheckoutData;
use App\Exceptions\CartChangedException;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Mail;

covers(Cart::class, CartLine::class);

function cart(): Cart
{
    return app(Cart::class);
}

it('starts empty', function () {
    expect(cart()->isEmpty())->toBeTrue()
        ->and(cart()->count())->toBe(0)
        ->and(cart()->total())->toBe(0)
        ->and(cart()->lines())->toBeEmpty();
});

it('adds products and sums totals and counts', function () {
    $a = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    $b = Product::factory()->create(['price' => 2550, 'stock' => 10]);

    cart()->add($a, 2);
    cart()->add($b, 1);

    expect(cart()->count())->toBe(3)
        ->and(cart()->total())->toBe(22550)
        ->and(cart()->isEmpty())->toBeFalse();
});

it('sums quantities when the same product is added twice', function () {
    $product = Product::factory()->create(['stock' => 10]);

    cart()->add($product, 2);
    cart()->add($product, 3);

    expect(cart()->lines())->toHaveCount(1)
        ->and(cart()->quantityOf($product->id))->toBe(5);
});

it('keeps the newer price when adding the same product again', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    cart()->add($product, 1);

    $product->update(['price' => 12000]);
    cart()->add($product->fresh(), 1);

    expect(cart()->total())->toBe(24000);
});

it('updates and removes lines', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    cart()->add($product, 1);

    cart()->update($product->id, 4);
    expect(cart()->count())->toBe(4);

    cart()->remove($product->id);
    expect(cart()->isEmpty())->toBeTrue();
});

it('clears the cart', function () {
    cart()->add(Product::factory()->create(), 1);

    cart()->clear();

    expect(cart()->isEmpty())->toBeTrue();
});

it('exposes lines with the product, quantity and seen price', function () {
    $product = Product::factory()->create(['price' => 1999, 'stock' => 10]);
    cart()->add($product, 3);

    $line = cart()->lines()->sole();

    expect($line)->toBeInstanceOf(CartLine::class)
        ->and($line->product->is($product))->toBeTrue()
        ->and($line->quantity)->toBe(3)
        ->and($line->price)->toBe(1999)
        ->and($line->lineTotal())->toBe(5997);
});

it('refresh updates a changed price and reports it', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 10]);
    cart()->add($product, 1);
    $product->update(['price' => 12000]);

    $messages = cart()->refresh();

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('Moka konvička')
        ->and(cart()->total())->toBe(12000);
});

it('refresh drops an unavailable product', function () {
    $product = Product::factory()->create(['name' => 'Matcha']);
    cart()->add($product, 1);
    $product->update(['is_available' => false]);

    $messages = cart()->refresh();

    expect($messages[0])->toContain('Matcha')
        ->and(cart()->isEmpty())->toBeTrue();
});

it('refresh drops a sold out product', function () {
    $product = Product::factory()->create(['name' => 'Decaf', 'stock' => 5]);
    cart()->add($product, 2);
    $product->update(['stock' => 0]);

    expect(cart()->refresh()[0])->toContain('Decaf')
        ->and(cart()->isEmpty())->toBeTrue();
});

it('leaves a deleted product out of the total, as it does from the lines', function () {
    $kept = Product::factory()->create(['price' => 10000]);
    $deleted = Product::factory()->create(['price' => 5000]);
    cart()->add($kept, 1);
    cart()->add($deleted, 1);
    $deleted->delete();

    expect(cart()->total())->toBe(10000)
        ->and(cart()->lines()->sum->lineTotal())->toBe(10000);
});

it('refresh drops a product that no longer exists', function () {
    $product = Product::factory()->create();
    cart()->add($product, 1);
    $product->delete();

    expect(cart()->refresh())->toHaveCount(1)
        ->and(cart()->isEmpty())->toBeTrue();
});

it('refresh clamps the quantity to stock', function () {
    $product = Product::factory()->create(['name' => 'Mlýnek', 'stock' => 5]);
    cart()->add($product, 4);
    $product->update(['stock' => 2]);

    $messages = cart()->refresh();

    expect($messages[0])->toContain('Mlýnek')
        ->and(cart()->quantityOf($product->id))->toBe(2);
});

it('refresh reports nothing when nothing changed', function () {
    cart()->add(Product::factory()->create(['stock' => 5]), 1);

    expect(cart()->refresh())->toBe([]);
});

it('refresh states the old and the new price', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 10]);
    cart()->add($product, 1);
    $product->update(['price' => 12000]);

    expect(cart()->refresh())->toBe([
        'Moka konvička: cena se změnila z '.Money::format(10000).' na '.Money::format(12000).'.',
    ]);
});

it('refresh keeps checking the lines after a dropped one', function (Closure $drop) {
    $dropped = Product::factory()->create(['name' => 'Matcha', 'stock' => 5]);
    $repriced = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 5]);
    cart()->add($dropped, 1);
    cart()->add($repriced, 1);
    $drop($dropped);
    $repriced->update(['price' => 12000]);

    $messages = cart()->refresh();

    expect($messages)->toHaveCount(2)
        ->and($messages[1])->toContain('Moka konvička')
        ->and(cart()->total())->toBe(12000);
})->with([
    'unavailable' => [fn (Product $product) => $product->update(['is_available' => false])],
    'sold out' => [fn (Product $product) => $product->update(['stock' => 0])],
    'deleted' => [fn (Product $product) => $product->delete()],
]);

it('refresh leaves a quantity equal to the stock alone', function () {
    $product = Product::factory()->create(['stock' => 3]);
    cart()->add($product, 3);

    expect(cart()->refresh())->toBe([])
        ->and(cart()->quantityOf($product->id))->toBe(3);
});

it('reports zero quantity for a product not in the cart', function () {
    expect(cart()->quantityOf(Product::factory()->create()->id))->toBe(0);
});

it('caps the orderable quantity by stock and by the shop limit', function () {
    config(['shop.max_quantity' => 99]);

    expect(Cart::maxQuantityFor(Product::factory()->make(['stock' => 5])))->toBe(5)
        ->and(Cart::maxQuantityFor(Product::factory()->make(['stock' => 500])))->toBe(99);
});

it('multiplies the seen price by the quantity for a line total', function () {
    $line = new CartLine(Product::factory()->make(), 3, 12550);

    expect($line->lineTotal())->toBe(37650);
});

it('persists in the session between instances', function () {
    $product = Product::factory()->create(['stock' => 5]);
    cart()->add($product, 2);

    app()->forgetScopedInstances();

    expect(cart()->quantityOf($product->id))->toBe(2);
});

it('keeps one token from the first product until the cart is cleared', function () {
    $product = Product::factory()->create(['stock' => 10]);

    cart()->add($product, 1);
    $token = session('cart_token');
    cart()->add($product, 1);

    expect($token)->toBeString()->not->toBeEmpty()
        ->and(cart()->token())->toBe($token);

    cart()->clear();

    expect(session()->has('cart_token'))->toBeFalse()
        ->and(cart()->token())->not->toBe($token);
});

it('fingerprints the content regardless of the order products were added in', function () {
    $a = Product::factory()->create(['stock' => 10]);
    $b = Product::factory()->create(['stock' => 10]);

    cart()->add($a, 1);
    cart()->add($b, 2);
    $fingerprint = cart()->fingerprint();

    cart()->clear();
    cart()->add($b, 2);
    cart()->add($a, 1);

    expect(cart()->fingerprint())->toBe($fingerprint);

    cart()->update($a->id, 3);

    expect(cart()->fingerprint())->not->toBe($fingerprint);
});

it('builds a cart from raw items and keeps the price the customer saw', function () {
    $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    $items = [$product->id => ['quantity' => 2, 'price' => 9000]];

    $fromItems = Cart::fromItems(new Store('x', new ArraySessionHandler(10)), $items, 'tok-1');

    $session = new Store('y', new ArraySessionHandler(10));
    $session->put('cart', $items);
    $reference = new Cart($session);

    expect($fromItems->items())->toBe($items)
        ->and($fromItems->token())->toBe('tok-1')
        ->and($fromItems->fingerprint())->toBe($reference->fingerprint());
});

it('lets PlaceOrder refuse a cart built from items with an outdated price', function () {
    Mail::fake();
    $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
    $cart = Cart::fromItems(new Store('x', new ArraySessionHandler(10)), [$product->id => ['quantity' => 1, 'price' => 9000]], 'tok-1');
    $data = new CheckoutData('Jana Nováková', 'jana@example.com', '+420 000 000 000', 'Dlouhá 12', 'Praha', '110 00', null);

    expect(fn () => app(PlaceOrder::class)->handle($cart, $data))->toThrow(CartChangedException::class);
});
