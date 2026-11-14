<?php

use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;

it('renders the confirmation e-mail with items, total and address', function () {
    $order = Order::factory()
        ->withItem(Product::factory()->create(['name' => 'Moka konvička', 'price' => 89900]), 2)
        ->create(['customer_name' => 'Jana Nováková', 'street' => 'Na Příkopě 12', 'city' => 'Praha', 'zip' => '110 00']);
    $mail = new OrderPlaced($order->fresh());

    $mail->assertHasSubject("Potvrzení objednávky č. {$order->number}");
    $mail->assertSeeInHtml('Moka konvička')
        ->assertSeeInHtml('2 ks')
        ->assertSeeInHtml(Money::format(179800), false)
        ->assertSeeInHtml(Money::format($order->fresh()->total), false)
        ->assertSeeInHtml('Na Příkopě 12');
});
