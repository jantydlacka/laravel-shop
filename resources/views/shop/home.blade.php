<x-layouts.shop>
    <x-category-nav :categories="$categories" />

    <h1 class="mb-6 text-2xl font-semibold">Novinky</h1>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        @foreach ($products as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</x-layouts.shop>
