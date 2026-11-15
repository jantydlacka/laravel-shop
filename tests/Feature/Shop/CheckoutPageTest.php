<?php

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Exceptions\OrderAlreadyPlacedException;
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
    $this->get(route('shop.checkout'))
        ->assertRedirect(route('shop.cart'))
        ->assertSessionMissing('status');
});

it('tells the customer the order was already sent when they come back to the checkout', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);
    fillCheckout()->call('submit');
    $order = Order::sole();

    $this->get(route('shop.checkout'))
        ->assertRedirect(route('shop.cart'))
        ->assertSessionHas('status', "Objednávka č. {$order->number} už byla odeslána, košík je prázdný.");
});

it('sends the customer to the cart when everything in it sold out before the checkout opened', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $product->update(['stock' => 0]);

    $this->get(route('shop.checkout'))
        ->assertRedirect(route('shop.cart'))
        ->assertSessionHas('status', 'Moka konvička je vyprodaný, odebrali jsme ho z košíku.');
});

it('sends the customer to the cart when everything in it sold out while filling in the form', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'stock' => 5]);
    app(Cart::class)->add($product, 1);
    $component = fillCheckout();
    $product->update(['stock' => 0]);

    $component->call('submit')->assertRedirect(route('shop.cart'));

    expect(session('status'))->toBe('Moka konvička je vyprodaný, odebrali jsme ho z košíku.')
        ->and(Order::count())->toBe(0);
});

it('refuses an order when the cart changed in another tab after the summary was shown', function () {
    $product = Product::factory()->create(['name' => 'Moka konvička', 'price' => 10000, 'stock' => 10]);
    app(Cart::class)->add($product, 1);
    $component = fillCheckout();
    app(Cart::class)->add($product, 5);

    $component->call('submit')
        ->assertNoRedirect()
        ->assertSee('Košík se mezitím změnil')
        ->assertSee('6× Moka konvička');

    expect(Order::count())->toBe(0);

    $component->call('submit')->assertRedirect();

    expect(Order::sole()->total)->toBe(60000);
});

it('sends the customer to the cart when a parallel request already placed the order', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);
    $order = Order::factory()->create();
    $this->mock(PlaceOrder::class)->shouldReceive('handle')->andThrow(new OrderAlreadyPlacedException($order));

    fillCheckout()->call('submit')->assertRedirect(route('shop.cart'));

    expect(session('status'))->toBe("Objednávka č. {$order->number} už byla odeslána, košík je prázdný.")
        ->and(session('checkout.last_order'))->toBe($order->number);
});

it('stops placing orders from one address after the hourly limit', function () {
    config(['shop.orders_per_hour' => 2]);
    $product = Product::factory()->create(['stock' => 10]);

    foreach (range(1, 2) as $i) {
        app(Cart::class)->add($product, 1);
        fillCheckout()->call('submit')->assertRedirect();
    }

    app(Cart::class)->add($product, 1);
    fillCheckout()->call('submit')
        ->assertNoRedirect()
        ->assertHasErrors(['submit' => 'Z vaší adresy přišlo v poslední hodině příliš mnoho objednávek. Zkuste to prosím později.'])
        ->assertSee('příliš mnoho objednávek');

    expect(Order::count())->toBe(2)
        ->and($product->fresh()->stock)->toBe(8);

    $this->travel(61)->minutes();
    fillCheckout()->call('submit')->assertRedirect();

    expect(Order::count())->toBe(3);
});

it('rejects a note longer than 2000 characters with a visible message', function () {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    fillCheckout(['note' => str_repeat('a', 2001)])->call('submit')
        ->assertHasErrors(['note' => 'Poznámka může mít nejvýš 2000 znaků.'])
        ->assertSee('Poznámka může mít nejvýš 2000 znaků.');

    expect(Order::count())->toBe(0);
});

it('shows the order summary', function () {
    app(Cart::class)->add(Product::factory()->create(['name' => 'Moka konvička', 'price' => 89900, 'stock' => 5]), 2);

    $this->get(route('shop.checkout'))
        ->assertOk()
        ->assertSee('Moka konvička')
        ->assertSee(str_replace(' ', "\u{a0}", '1 798 Kč'));
});

it('places the order and redirects to a signed confirmation', function () {
    $this->freezeTime();
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    $component = fillCheckout()->call('submit')->assertHasNoErrors();

    $order = Order::sole();
    $component->assertRedirect(URL::temporarySignedRoute('shop.order.confirmation', now()->addHour(), ['order' => $order]));
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

it('names the field in Czech when the input is too long', function (string $field, int $max, string $message) {
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);

    $value = $field === 'email' ? str_repeat('a', $max).'@example.com' : str_repeat('a', $max + 1);

    fillCheckout([$field => $value])->call('submit')->assertHasErrors([$field => $message]);
})->with([
    ['name', 255, 'Pole jméno a příjmení smí mít nejvýš 255 znaků.'],
    ['email', 255, 'Pole e-mail smí mít nejvýš 255 znaků.'],
    ['phone', 30, 'Pole telefon smí mít nejvýš 30 znaků.'],
    ['street', 255, 'Pole ulice a číslo domu smí mít nejvýš 255 znaků.'],
    ['city', 255, 'Pole město smí mít nejvýš 255 znaků.'],
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

it('lets the confirmation link expire after an hour', function () {
    $this->freezeTime();
    app(Cart::class)->add(Product::factory()->create(['stock' => 5]), 1);
    $url = fillCheckout()->call('submit')->effects['redirect'];

    $this->travel(59)->minutes();
    $this->get($url)->assertOk();

    $this->travel(2)->minutes();
    $this->get($url)->assertForbidden();
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
