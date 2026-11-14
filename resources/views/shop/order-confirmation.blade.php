@php use App\Support\Money; @endphp
<x-layouts.shop title="Objednávka přijata">
    <div class="mx-auto max-w-2xl rounded-xl border border-stone-200 bg-white p-8">
        <h1 class="mb-2 text-2xl font-semibold">Děkujeme, objednávka je přijatá</h1>
        <p class="mb-6 text-stone-600">
            Číslo objednávky: <strong data-order-number>{{ $order->number }}</strong>.
            Potvrzení vám pošleme na {{ $order->email }}.
        </p>

        <table class="mb-6 w-full text-sm">
            <tbody class="divide-y divide-stone-200">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="py-2">{{ $item->product_name }}</td>
                        <td class="py-2 text-right">{{ $item->quantity }} ks</td>
                        <td class="py-2 text-right">{{ Money::format($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <td class="pt-4" colspan="2">Celkem s DPH</td>
                    <td class="pt-4 text-right">{{ Money::format($order->total) }}</td>
                </tr>
                <tr class="text-stone-500">
                    <td colspan="2">z toho DPH {{ $order->vat_rate }} %</td>
                    <td class="text-right">{{ Money::format($order->vat_total) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="text-sm text-stone-600">
            Doručíme na adresu: {{ $order->customer_name }}, {{ $order->street }}, {{ $order->zip }} {{ $order->city }}.
        </p>

        <a href="{{ route('shop.home') }}" class="mt-6 inline-block underline">Pokračovat v nákupu</a>
    </div>
</x-layouts.shop>
