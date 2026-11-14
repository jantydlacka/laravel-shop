<x-layouts.shop :title="$category->name">
    <x-category-nav :categories="$categories" :current="$category" />

    <h1 class="mb-6 text-2xl font-semibold">{{ $category->name }}</h1>

    @if ($products->isEmpty())
        <p class="text-stone-500">V této kategorii zatím nic není.</p>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    @endif
</x-layouts.shop>
