@props(['title' => null])
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' – ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="{{ route('shop.home') }}" class="text-xl font-semibold tracking-tight">{{ config('app.name') }}</a>
            <a href="#" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white">
                Košík (<span>0</span>)
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-6xl px-4 py-8 text-sm text-stone-500">
        Ukázkový e-shop – objednávky nejsou závazné.
    </footer>
</body>
</html>
