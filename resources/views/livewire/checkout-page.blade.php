<div>
    <h1 class="mb-6 text-2xl font-semibold">Pokladna</h1>

    @if ($notices)
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
            <p class="mb-1 font-medium">Od vložení do košíku se něco změnilo. Zkontrolujte prosím objednávku a odešlete ji znovu:</p>
            <ul class="list-disc pl-5">
                @foreach ($notices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-8 md:grid-cols-[1fr_20rem]">
        <form wire:submit="submit" class="grid gap-4 rounded-xl border border-stone-200 bg-white p-6 sm:grid-cols-2">
            @foreach ([
                'name' => ['Jméno a příjmení', 'text', 'name'],
                'email' => ['E-mail', 'email', 'email'],
                'phone' => ['Telefon', 'tel', 'tel'],
                'street' => ['Ulice a číslo domu', 'text', 'street-address'],
                'city' => ['Město', 'text', 'address-level2'],
                'zip' => ['PSČ', 'text', 'postal-code'],
            ] as $field => [$label, $type, $autocomplete])
                <div @class(['sm:col-span-2' => in_array($field, ['name', 'street'])])>
                    <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    <input id="{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}" autocomplete="{{ $autocomplete }}"
                           class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    @error($field) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <label for="note" class="mb-1 block text-sm font-medium">Poznámka (nepovinné)</label>
                <textarea id="note" wire:model="note" rows="3" class="w-full rounded-lg border border-stone-300 px-3 py-2"></textarea>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white disabled:opacity-50">
                    Odeslat objednávku
                </button>
                <p class="mt-2 text-sm text-stone-500">Doprava zdarma. Ukázkový obchod – nic se neplatí.</p>
            </div>
        </form>

        <aside class="h-fit rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="mb-4 font-semibold">Shrnutí</h2>
            <ul class="mb-4 space-y-2 text-sm">
                @foreach ($lines as $line)
                    <li class="flex justify-between gap-2" wire:key="sum-{{ $line->product->id }}">
                        <span>{{ $line->quantity }}× {{ $line->product->name }}</span>
                        <span class="whitespace-nowrap">{{ \App\Support\Money::format($line->lineTotal()) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="flex justify-between border-t border-stone-200 pt-4 font-semibold">
                <span>Celkem s DPH</span>
                <span>{{ \App\Support\Money::format($total) }}</span>
            </p>
            <a href="{{ route('shop.cart') }}" class="mt-4 inline-block text-sm underline">Upravit košík</a>
        </aside>
    </div>
</div>
