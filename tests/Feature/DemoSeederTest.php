<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

it('seeds a browsable demo catalog and the admin user', function () {
    $this->seed();

    expect(Category::count())->toBeGreaterThanOrEqual(3)
        ->and(Product::count())->toBeGreaterThanOrEqual(10)
        ->and(Product::where('stock', 0)->exists())->toBeTrue()
        ->and(Product::where('is_available', false)->exists())->toBeTrue()
        ->and(User::where('email', 'admin@example.com')->exists())->toBeTrue();
});

it('can be run twice', function () {
    $this->seed();
    $counts = [Category::count(), Product::count(), User::count()];

    $this->seed();

    expect([Category::count(), Product::count(), User::count()])->toBe($counts);
});

it('points every product image at a file shipped in the repository', function () {
    $this->seed();

    expect(Product::count())->toBeGreaterThan(0);

    Product::all()->each(function (Product $product) {
        expect($product->image_path)->toStartWith('images/demo/')
            ->and(public_path($product->image_path))->toBeFile();
    });
});
