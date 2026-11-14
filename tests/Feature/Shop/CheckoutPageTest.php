<?php

use App\Cart\Cart;
use App\Livewire\CheckoutPage;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
});

function validForm(): array
{
    return [
        'name' => 'Jana Nováková',
        'email' => 'jana@example.com',
        'phone' => '+420 777 123 456',
        'street' => 'Dlouhá 12',
        'city' => 'Praha',
        'zip' => '11000',
        'note' => '',
    ];
}

function fillCheckout(array $overrides = [])
{
    $component = livewire(CheckoutPage::class);
    foreach (array_merge(validForm(), $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

it('redirects to the cart when the cart is empty', function () {
    $this->get(route('shop.checkout'))->assertRedirect(route('shop.cart'));
});

it('shows the order summary', function () {
    app(Cart::class)->add(Product::factory()->create(['name' => 'Moka konvička', 'price' => 89900, 'stock' => 5]), 2);

    $this->get(route('shop.checkout'))
        ->assertOk()
        ->assertSee('Moka konvička')
        ->assertSee(str_replace(' ', "\u{a0}", '1 798 Kč'));
});

it('places the order and redirects to a signed confirmation', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    $component = fillCheckout()->call('submit')->assertHasNoErrors();

    $order = Order::sole();
    $component->assertRedirect(URL::signedRoute('shop.order.confirmation', $order));
});

it('validates required fields with a message of their own', function (string $field, string $message) {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    fillCheckout([$field => ''])->call('submit')->assertHasErrors([$field => $message]);

    expect(Order::count())->toBe(0);
})->with([
    ['name', 'Vyplňte jméno a příjmení.'],
    ['email', 'Vyplňte e-mail.'],
    ['phone', 'Vyplňte telefon.'],
    ['street', 'Vyplňte ulici a číslo domu.'],
    ['city', 'Vyplňte město.'],
    ['zip', 'Vyplňte PSČ.'],
]);

it('validates the e-mail format', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    fillCheckout(['email' => 'jana-at-example'])->call('submit')->assertHasErrors(['email' => 'Zadejte platný e-mail.']);
});

it('accepts Czech zip codes with or without a space', function (string $zip, bool $valid) {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    $component = fillCheckout(['zip' => $zip])->call('submit');

    $valid ? $component->assertHasNoErrors('zip') : $component->assertHasErrors('zip');
})->with([
    ['110 00', true],
    ['11000', true],
    ['1100', false],
    ['110000', false],
    ['abcde', false],
]);

it('shows Czech validation messages', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    fillCheckout(['email' => 'x'])->call('submit')->assertSee('Zadejte platný e-mail');
});

it('shows what changed and keeps the form when the cart went stale', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $component = fillCheckout();
    $product->update(['price' => 12000]);

    $component->call('submit')
        ->assertNoRedirect()
        ->assertSee('Moka konvička')
        ->assertSee('cena se změnila')
        ->assertSet('name', 'Jana Nováková')
        ->assertSet('street', 'Dlouhá 12');

    expect(Order::count())->toBe(0);
});

it('sends a repeated submit back to the cart with a message', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);
    $component = fillCheckout();
    $component->call('submit');

    $component->call('submit')->assertRedirect(route('shop.cart'));

    expect(Order::count())->toBe(1)
        ->and(session('status'))->toContain('prázdný');
});

it('shows the confirmation on a signed url', function () {
    $order = Order::factory()->withItem(Product::factory()->create(['name' => 'Moka konvička']))->create();

    $this->get(URL::signedRoute('shop.order.confirmation', $order))
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('Moka konvička');
});

it('rejects an unsigned confirmation url', function () {
    $order = Order::factory()->create();

    $this->get(route('shop.order.confirmation', $order))->assertForbidden();
});

it('rejects a confirmation url with a changed order number', function () {
    $mine = Order::factory()->create();
    $other = Order::factory()->create();
    $url = URL::signedRoute('shop.order.confirmation', $mine);

    $this->get(str_replace($mine->number, $other->number, $url))->assertForbidden();
});
