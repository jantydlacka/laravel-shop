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

it('does not turn Markdown typed by the customer into links', function () {
    $order = Order::factory()
        ->withItem(Product::factory()->create(), 1)
        ->create([
            'customer_name' => '[Zaplaťte zde](https://attacker.example)',
            'street' => '<https://attacker.example/street>',
            'city' => '[x][1]',
        ]);

    $html = (new OrderPlaced($order->fresh()))->render();

    expect($html)->not->toContain('href="https://attacker.example')
        ->and($html)->toContain('[Zaplaťte zde](https://attacker.example)');
});

it('keeps a product name with a pipe in one table cell', function () {
    $order = Order::factory()
        ->withItem(Product::factory()->create(['name' => 'Káva | extra', 'price' => 12300]), 1)
        ->create();

    $html = (new OrderPlaced($order->fresh()))->render();

    expect($html)->toMatch('/>Káva \| extra<\/td>/')
        ->and($html)->toContain(Money::format(12300));
});
