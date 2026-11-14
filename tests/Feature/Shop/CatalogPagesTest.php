<?php

use App\Models\Category;
use App\Models\Product;

function nbsp(string $text): string
{
    return str_replace(' ', "\u{a0}", $text);
}

it('lists categories and available products on the home page', function () {
    $category = Category::factory()->create(['name' => 'Kávovary']);
    Product::factory()->for($category)->create(['name' => 'Moka konvička', 'price' => 129900]);
    Product::factory()->for($category)->unavailable()->create(['name' => 'Stažený mlýnek']);

    $this->get(route('shop.home'))
        ->assertOk()
        ->assertSee('Kávovary')
        ->assertSee('Moka konvička')
        ->assertSee(nbsp('1 299 Kč'))
        ->assertDontSee('Stažený mlýnek');
});

it('shows only available products of the category', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create(['name' => 'Patří sem']);
    Product::factory()->for($category)->unavailable()->create(['name' => 'Nedostupný']);
    Product::factory()->create(['name' => 'Jiná kategorie']);

    $this->get(route('shop.category', $category))
        ->assertOk()
        ->assertSee('Patří sem')
        ->assertDontSee('Nedostupný')
        ->assertDontSee('Jiná kategorie');
});

it('shows the product detail with price and add-to-cart button', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 123450, 'stock' => 3]);

    $this->get(route('shop.product', $product))
        ->assertOk()
        ->assertSee('Moka konvička')
        ->assertSee(nbsp('1 234,50 Kč'))
        ->assertSee('Do košíku')
        ->assertDontSee('Vyprodáno');
});

it('returns 404 for an unavailable product detail', function () {
    $product = Product::factory()->unavailable()->create();

    $this->get(route('shop.product', $product))->assertNotFound();
});

it('shows sold out without an add-to-cart button when stock is zero', function () {
    $product = Product::factory()->outOfStock()->create();

    $this->get(route('shop.product', $product))
        ->assertOk()
        ->assertSee('Vyprodáno')
        ->assertDontSee('Do košíku');
});

it('returns 404 for unknown slugs', function () {
    $this->get('/produkt/neexistuje')->assertNotFound();
    $this->get('/kategorie/neexistuje')->assertNotFound();
});
