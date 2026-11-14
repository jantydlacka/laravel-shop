@props(['categories', 'current' => null])
<nav class="mb-8 flex flex-wrap gap-2">
    @foreach ($categories as $category)
        <a href="{{ route('shop.category', $category) }}"
           @class([
               'rounded-full border px-4 py-1.5 text-sm',
               'border-stone-900 bg-stone-900 text-white' => $current?->is($category),
               'border-stone-300 bg-white hover:border-stone-500' => ! $current?->is($category),
           ])>{{ $category->name }}</a>
    @endforeach
</nav>
