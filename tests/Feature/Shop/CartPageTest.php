<?php

use App\Cart\Cart;
use App\Livewire\AddToCart;
use App\Livewire\CartCounter;
use App\Livewire\CartPage;
use App\Models\Product;

use function Pest\Livewire\livewire;

it('adds a product to the cart and notifies the counter', function () {
    $product = Product::factory()->create(['stock' => 5]);

    livewire(AddToCart::class, ['product' => $product])
        ->set('quantity', 2)
        ->call('add')
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated');

    expect(app(Cart::class)->quantityOf($product->id))->toBe(2);
});

it('rejects an invalid quantity and leaves the cart unchanged', function (mixed $quantity) {
    $product = Product::factory()->create(['stock' => 5]);

    livewire(AddToCart::class, ['product' => $product])
        ->set('quantity', $quantity)
        ->call('add')
        ->assertHasErrors('quantity')
        ->assertNotDispatched('cart-updated');

    expect(app(Cart::class)->isEmpty())->toBeTrue();
})->with([
    'zero' => [0],
    'negative' => [-1],
    'decimal' => [1.5],
    'text' => ['abc'],
    'above stock' => [6],
]);

it('rejects more than the maximum quantity even with enough stock', function () {
    $product = Product::factory()->create(['stock' => 500]);

    livewire(AddToCart::class, ['product' => $product])
        ->set('quantity', 100)
        ->call('add')
        ->assertHasErrors('quantity');

    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

it('counts what is already in the cart against stock', function () {
    $product = Product::factory()->create(['stock' => 5]);
    app(Cart::class)->add($product, 4);

    livewire(AddToCart::class, ['product' => $product])
        ->set('quantity', 2)
        ->call('add')
        ->assertHasErrors('quantity');

    expect(app(Cart::class)->quantityOf($product->id))->toBe(4);
});

it('shows a Czech validation message', function () {
    $product = Product::factory()->create(['stock' => 5]);

    livewire(AddToCart::class, ['product' => $product])
        ->set('quantity', 0)
        ->call('add')
        ->assertSee('Množství musí být');
});

it('shows the item count in the counter', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 3);

    livewire(CartCounter::class)->assertSee('3');
});

it('shows lines and the total on the cart page', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 89900, 'stock' => 5]);
    app(Cart::class)->add($product, 2);

    $this->get(route('shop.cart'))
        ->assertOk()
        ->assertSeeLivewire(CartPage::class)
        ->assertSee('Moka konvička')
        ->assertSee(str_replace(' ', "\u{a0}", '1 798 Kč'));
});

it('shows an empty state', function () {
    $this->get(route('shop.cart'))->assertOk()->assertSee('Košík je prázdný');
});

it('changes the quantity on the cart page', function () {
    $product = Product::factory()->create(['stock' => 5]);
    app(Cart::class)->add($product, 1);

    livewire(CartPage::class)
        ->set("quantities.{$product->id}", 3)
        ->assertHasNoErrors()
        ->assertDispatched('cart-updated');

    expect(app(Cart::class)->quantityOf($product->id))->toBe(3);
});

it('rejects an invalid quantity on the cart page', function (mixed $quantity) {
    $product = Product::factory()->create(['stock' => 5]);
    app(Cart::class)->add($product, 1);

    livewire(CartPage::class)
        ->set("quantities.{$product->id}", $quantity)
        ->assertHasErrors("quantities.{$product->id}");

    expect(app(Cart::class)->quantityOf($product->id))->toBe(1);
})->with([[0], [-1], [1.5], [6], [100]]);

it('removes a line on the cart page', function () {
    $product = Product::factory()->create(['stock' => 5]);
    app(Cart::class)->add($product, 1);

    livewire(CartPage::class)
        ->call('remove', $product->id)
        ->assertDispatched('cart-updated')
        ->assertSee('Košík je prázdný');

    expect(app(Cart::class)->isEmpty())->toBeTrue();
});

it('shows what changed since the product was added', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $product->update(['price' => 12000]);

    livewire(CartPage::class)->assertSee('Moka konvička')->assertSee('změnila');
});
