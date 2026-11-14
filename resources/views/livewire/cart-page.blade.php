<div>
    <h1 class="mb-6 text-2xl font-semibold">Košík</h1>

    @if ($notices)
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="mb-1 font-medium">Od vložení do košíku se něco změnilo:</p>
            <ul class="list-disc pl-5">
                @foreach ($notices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($lines->isEmpty())
        <p class="text-stone-500">Košík je prázdný.</p>
        <a href="{{ route('shop.home') }}" class="mt-4 inline-block underline">Zpět do obchodu</a>
    @else
        <div class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
            @foreach ($lines as $line)
                <div class="flex flex-wrap items-center gap-4 p-4" wire:key="line-{{ $line->product->id }}">
                    <a href="{{ route('shop.product', $line->product) }}" class="flex-1 font-medium hover:underline">{{ $line->product->name }}</a>
                    <span class="text-sm text-stone-500">{{ \App\Support\Money::format($line->price) }} / ks</span>
                    <div>
                        <label for="qty-{{ $line->product->id }}" class="sr-only">Množství</label>
                        <input id="qty-{{ $line->product->id }}" type="number" min="1" step="1"
                               wire:model.live.debounce.500ms="quantities.{{ $line->product->id }}"
                               class="w-20 rounded-lg border border-stone-300 px-3 py-2 text-center">
                    </div>
                    <span class="w-28 text-right font-semibold">{{ \App\Support\Money::format($line->lineTotal()) }}</span>
                    <button type="button" wire:click="remove({{ $line->product->id }})" class="text-sm text-red-700 hover:underline">Odebrat</button>
                    @error('quantities.'.$line->product->id)
                        <p class="w-full text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between">
            <p class="text-lg">Celkem s DPH: <strong>{{ \App\Support\Money::format($total) }}</strong></p>
        </div>
    @endif
</div>
