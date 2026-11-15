<?php

namespace App\Livewire;

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Exceptions\CartChangedException;
use App\Exceptions\EmptyCartException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('Pokladna')]
class CheckoutPage extends Component
{
    // Message keys carry the field name: Livewire merges them into one list for the whole component.
    #[Validate('required|string|max:255', message: ['name.required' => 'Vyplňte jméno a příjmení.'], as: 'jméno a příjmení')]
    public string $name = '';

    #[Validate('required|email|max:255', message: ['email.required' => 'Vyplňte e-mail.', 'email.email' => 'Zadejte platný e-mail.'], as: 'e-mail')]
    public string $email = '';

    #[Validate('required|string|max:30', message: ['phone.required' => 'Vyplňte telefon.'], as: 'telefon')]
    public string $phone = '';

    #[Validate('required|string|max:255', message: ['street.required' => 'Vyplňte ulici a číslo domu.'], as: 'ulice a číslo domu')]
    public string $street = '';

    #[Validate('required|string|max:255', message: ['city.required' => 'Vyplňte město.'], as: 'město')]
    public string $city = '';

    #[Validate(['required', 'regex:/^\d{3}\s?\d{2}$/'], message: ['zip.required' => 'Vyplňte PSČ.', 'zip.regex' => 'PSČ má pět číslic, např. 110 00.'], as: 'PSČ')]
    public string $zip = '';

    #[Validate('nullable|string|max:2000', message: ['note.max' => 'Poznámka může mít nejvýš 2000 znaků.'], as: 'poznámka')]
    public string $note = '';

    /** @var list<string> */
    public array $notices = [];

    public function mount(Cart $cart): mixed
    {
        $this->notices = $cart->refresh();

        if (! $cart->isEmpty()) {
            return null;
        }

        if ($this->notices !== []) {
            return $this->backToCart($this->notices);
        }

        // Back button or reload after an order: the cart is already empty, say why.
        if (session()->has('checkout.last_order')) {
            session()->flash('status', 'Objednávka č. '.session('checkout.last_order').' už byla odeslána, košík je prázdný.');
        }

        return $this->redirectRoute('shop.cart');
    }

    public function submit(PlaceOrder $placeOrder, Cart $cart): void
    {
        $this->validate();

        try {
            $order = $placeOrder->handle($cart, new CheckoutData(
                name: $this->name,
                email: $this->email,
                phone: $this->phone,
                street: $this->street,
                city: $this->city,
                zip: $this->zip,
                note: $this->note !== '' ? $this->note : null,
            ));
        } catch (CartChangedException $e) {
            $this->notices = $e->messages;

            if ($cart->isEmpty()) {
                $this->backToCart($e->messages);
            }

            return;
        } catch (EmptyCartException) {
            session()->flash('status', 'Košík je prázdný – objednávka už byla odeslána, nebo jste z něj vše odebrali.');
            $this->redirectRoute('shop.cart');

            return;
        }

        session()->put('checkout.last_order', $order->number);
        $this->redirect(URL::temporarySignedRoute('shop.order.confirmation', now()->addHour(), ['order' => $order]));
    }

    /**
     * Nothing left to order: an empty form with a zero total would only invite another submit.
     *
     * @param  list<string>  $notices
     */
    private function backToCart(array $notices): mixed
    {
        session()->flash('status', implode(' ', $notices));

        return $this->redirectRoute('shop.cart');
    }

    public function render(Cart $cart): View
    {
        return view('livewire.checkout-page', [
            'lines' => $cart->lines(),
            'total' => $cart->total(),
        ]);
    }
}
