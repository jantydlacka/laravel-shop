@php use App\Support\Money; @endphp
<x-mail::message>
# Děkujeme za objednávku

Dobrý den, {{ $order->customer_name }},

přijali jsme vaši objednávku č. **{{ $order->number }}**. Ozveme se, jakmile ji odešleme.

<x-mail::table>
| Položka | Množství | Cena |
|:--------|:--------:|-----:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} ks | {{ Money::format($item->line_total) }} |
@endforeach
| **Celkem s DPH** | | **{{ Money::format($order->total) }}** |
</x-mail::table>

Z toho DPH {{ $order->vat_rate }} %: {{ Money::format($order->vat_total) }}. Doprava zdarma.

**Doručovací adresa:**
{{ $order->customer_name }}, {{ $order->street }}, {{ $order->zip }} {{ $order->city }}

S pozdravem,<br>
{{ config('app.name') }}
</x-mail::message>
