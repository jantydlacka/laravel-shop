@props(['product'])
<a href="{{ route('shop.product', $product) }}"
   class="group flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white transition hover:shadow-md">
    <div class="aspect-square bg-stone-100">
        @if ($product->image_url)
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-1 p-4">
        <h3 class="font-medium group-hover:underline">{{ $product->name }}</h3>
        <p class="mt-auto flex items-center justify-between">
            <span class="font-semibold">{{ \App\Support\Money::format($product->price) }}</span>
            @unless ($product->isInStock())
                <span class="text-sm text-red-700">Vyprodáno</span>
            @endunless
        </p>
    </div>
</a>
