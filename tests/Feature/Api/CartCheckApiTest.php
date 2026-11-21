<?php

use App\Models\Product;
use App\Support\Money;

function checkCart(array $items)
{
    return test()->postJson('/api/cart/check', ['items' => $items]);
}

it('returns the cart unchanged when nothing changed', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 10000]);

    checkCart([['product_id' => $product->id, 'quantity' => 2, 'price' => 10000]])
        ->assertOk()
        ->assertExactJson([
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 10000]],
            'messages' => [],
        ]);
});

it('drops a product that sold out', function () {
    $product = Product::factory()->outOfStock()->create(['price' => 10000]);

    checkCart([['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]])
        ->assertOk()
        ->assertExactJson([
            'items' => [],
            'messages' => ["{$product->name} je vyprodaný, odebrali jsme ho z košíku."],
        ]);
});

it('drops a product that was withdrawn', function () {
    $product = Product::factory()->unavailable()->create(['price' => 10000]);

    checkCart([['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]])
        ->assertOk()
        ->assertExactJson([
            'items' => [],
            'messages' => ["{$product->name} už není v nabídce, odebrali jsme ho z košíku."],
        ]);
});

it('drops an unknown product with a message instead of a validation error', function () {
    checkCart([['product_id' => 999999, 'quantity' => 1, 'price' => 10000]])
        ->assertOk()
        ->assertExactJson([
            'items' => [],
            'messages' => ['Jeden z produktů už není v nabídce, odebrali jsme ho z košíku.'],
        ]);
});

it('lowers quantity to the stock', function () {
    $product = Product::factory()->create(['stock' => 2, 'price' => 10000]);

    checkCart([['product_id' => $product->id, 'quantity' => 5, 'price' => 10000]])
        ->assertOk()
        ->assertExactJson([
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 10000]],
            'messages' => ["{$product->name}: skladem je jen 2 ks, množství jsme snížili."],
        ]);
});

it('updates a changed price', function () {
    $product = Product::factory()->create(['stock' => 5, 'price' => 12000]);

    checkCart([['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]])
        ->assertOk()
        ->assertExactJson([
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 12000]],
            'messages' => ["{$product->name}: cena se změnila z ".Money::format(10000).' na '.Money::format(12000).'.'],
        ]);
});

it('rejects quantity above the limit', function () {
    $product = Product::factory()->create(['stock' => 500, 'price' => 10000]);
    $over = (int) config('shop.max_quantity') + 1;

    checkCart([['product_id' => $product->id, 'quantity' => $over, 'price' => 10000]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);
});

it('rejects an empty or malformed cart', function () {
    $this->postJson('/api/cart/check', [])->assertStatus(422)->assertJsonValidationErrors(['items']);
    checkCart([['product_id' => 1, 'quantity' => 1]])->assertStatus(422)->assertJsonValidationErrors(['items.0.price']);
});

it('rejects more than 100 lines', function () {
    $lines = array_map(fn (int $id) => ['product_id' => $id, 'quantity' => 1, 'price' => 100], range(1, 101));

    checkCart($lines)->assertStatus(422)->assertJsonValidationErrors(['items']);
});
