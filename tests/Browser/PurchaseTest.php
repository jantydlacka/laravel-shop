<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

it('buys a product as a guest', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'stock' => 5]);

    $page = visit('/');

    $page->click('Moka konvička')
        ->assertPathIs("/produkt/{$product->slug}")
        ->fill('quantity', '2')
        ->press('Do košíku')
        ->assertSee('Přidáno do košíku.')
        ->assertSeeIn('[data-cart-count]', '2')
        ->click('Přejít do košíku')
        ->assertPathIs('/kosik')
        ->click('Pokračovat k objednávce')
        ->assertPathIs('/pokladna')
        ->fill('name', 'Jana Nováková')
        ->fill('email', 'jana@example.com')
        ->fill('phone', '777 123 456')
        ->fill('street', 'Na Příkopě 12')
        ->fill('city', 'Praha')
        ->fill('zip', '110 00')
        ->press('Odeslat objednávku')
        ->assertPathBeginsWith('/objednavka/');

    $order = Order::sole();

    $page->assertSee($order->number)
        ->assertSeeIn('[data-cart-count]', '0')
        ->assertNoJavaScriptErrors();

    expect($product->fresh()->stock)->toBe(3);
});

it('lets the admin start processing an order', function () {
    User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);
    $order = Order::factory()->withItem(Product::factory()->create())->create();

    $page = visit('/admin/login');

    $page->fill('[id="form.email"]', 'admin@example.com')
        ->fill('[id="form.password"]', 'password')
        ->press('Přihlásit se')
        ->assertSee('Nástěnka')
        ->navigate("/admin/orders/{$order->id}")
        ->assertSee("Objednávka {$order->number}")
        ->assertSee('Nová')
        ->click('Začít zpracovávat')
        ->click('.fi-modal-window [type="submit"]')
        ->assertSee('Objednávka je ve stavu „Zpracovává se“.')
        ->assertDontSee('Začít zpracovávat')
        ->assertNoJavaScriptErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});
