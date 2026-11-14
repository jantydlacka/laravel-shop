<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;

it('excludes unavailable products from the available scope', function () {
    $visible = Product::factory()->create();
    Product::factory()->create(['is_available' => false]);

    expect(Product::available()->pluck('id')->all())->toBe([$visible->id]);
});

it('is not in stock when stock is zero', function () {
    expect(Product::factory()->make(['stock' => 0])->isInStock())->toBeFalse()
        ->and(Product::factory()->make(['stock' => 1])->isInStock())->toBeTrue();
});

it('refuses to delete a category that has products', function () {
    $category = Category::factory()->has(Product::factory())->create();

    $category->delete();
})->throws(QueryException::class);

it('uses slugs as route keys', function () {
    expect((new Category)->getRouteKeyName())->toBe('slug')
        ->and((new Product)->getRouteKeyName())->toBe('slug');
});

it('casts money and stock to integers', function () {
    $product = Product::factory()->create(['price' => '129900', 'stock' => '3']);

    expect($product->fresh()->price)->toBe(129900)
        ->and($product->fresh()->stock)->toBe(3)
        ->and($product->fresh()->is_available)->toBeTrue();
});
