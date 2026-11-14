<x-layouts.shop :title="$product->name">
    <p class="mb-6 text-sm text-stone-500">
        <a href="{{ route('shop.home') }}" class="hover:underline">Obchod</a>
        /
        <a href="{{ route('shop.category', $product->category) }}" class="hover:underline">{{ $product->category->name }}</a>
    </p>

    <div class="grid gap-8 md:grid-cols-2">
        <div class="aspect-square overflow-hidden rounded-xl bg-stone-100">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
            @endif
        </div>

        <div class="flex flex-col gap-4">
            <h1 class="text-3xl font-semibold">{{ $product->name }}</h1>
            <p class="text-2xl font-semibold">{{ \App\Support\Money::format($product->price) }}</p>
            <p class="text-sm text-stone-500">Cena včetně DPH {{ config('shop.vat_rate') }} %</p>

            @if ($product->isInStock())
                <p class="text-sm text-green-700">Skladem {{ $product->stock }} ks</p>
                <livewire:add-to-cart :product="$product" />
            @else
                <p class="font-medium text-red-700">Vyprodáno</p>
            @endif

            <div class="prose mt-4 text-stone-700">{{ $product->description }}</div>
        </div>
    </div>
</x-layouts.shop>
