<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\DemoOrderSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

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
    $counts = [Category::count(), Product::count(), User::count(), Order::count()];

    $this->seed();

    expect([Category::count(), Product::count(), User::count(), Order::count()])->toBe($counts);
});

it('leaves existing products alone when run again', function () {
    $this->seed();
    Product::where('slug', 'earl-grey')->update(['description' => 'Upraveno v adminu', 'image_path' => 'products/upload.png']);
    Category::where('slug', 'caj')->update(['name' => 'Čaje']);
    $stock = Product::pluck('stock', 'slug')->all();

    $this->seed();

    expect(Product::pluck('stock', 'slug')->all())->toBe($stock)
        ->and(Product::where('slug', 'earl-grey')->sole())
        ->description->toBe('Upraveno v adminu')
        ->image_path->toBe('products/upload.png')
        ->and(Category::where('slug', 'caj')->value('name'))->toBe('Čaje');
});

it('puts every product image on the public disk', function () {
    $this->seed();

    expect(Product::count())->toBeGreaterThan(0);

    Product::all()->each(function (Product $product) {
        expect($product->image_path)->toBe("products/{$product->slug}.svg");
        Storage::disk('public')->assertExists($product->image_path);
    });
});

it('seeds orders in every status and takes their goods off the stock', function () {
    $this->seed(DemoCatalogSeeder::class);
    $stockBefore = Product::pluck('stock', 'id');

    $this->seed(DemoOrderSeeder::class);

    expect(Order::count())->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(8)
        ->and(Order::pluck('status')->unique()->sortBy->value->values()->all())
        ->toEqualCanonicalizing(OrderStatus::cases());

    $held = OrderItem::query()
        ->whereHas('order', fn ($query) => $query->where('status', '!=', OrderStatus::Cancelled))
        ->get()
        ->groupBy('product_id')
        ->map->sum('quantity');

    Product::all()->each(function (Product $product) use ($stockBefore, $held) {
        expect($product->stock)->toBe($stockBefore[$product->id] - ($held[$product->id] ?? 0));
    });
});
