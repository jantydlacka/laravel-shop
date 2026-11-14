<form wire:submit="add" class="flex flex-col gap-2">
    <div class="flex items-center gap-3">
        <label for="quantity" class="sr-only">Množství</label>
        <input id="quantity" type="number" wire:model="quantity" min="1" step="1"
               class="w-20 rounded-lg border border-stone-300 px-3 py-2.5 text-center">
        <button type="submit" wire:loading.attr="disabled"
                class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white disabled:opacity-50">
            Do košíku
        </button>
    </div>
    @error('quantity')
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
    @if ($added)
        <p class="text-sm text-green-700">Přidáno do košíku. <a href="{{ route('shop.cart') }}" class="underline">Přejít do košíku</a></p>
    @endif
</form>
