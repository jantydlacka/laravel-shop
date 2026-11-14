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
    #[Validate('required|string|max:255', message: ['required' => 'Vyplňte jméno a příjmení.'])]
    public string $name = '';

    #[Validate('required|email|max:255', message: ['required' => 'Vyplňte e-mail.', 'email' => 'Zadejte platný e-mail.'])]
    public string $email = '';

    #[Validate('required|string|max:30', message: ['required' => 'Vyplňte telefon.'])]
    public string $phone = '';

    #[Validate('required|string|max:255', message: ['required' => 'Vyplňte ulici a číslo domu.'])]
    public string $street = '';

    #[Validate('required|string|max:255', message: ['required' => 'Vyplňte město.'])]
    public string $city = '';

    #[Validate(['required', 'regex:/^\d{3}\s?\d{2}$/'], message: ['required' => 'Vyplňte PSČ.', 'regex' => 'PSČ má pět číslic, např. 110 00.'])]
    public string $zip = '';

    #[Validate('nullable|string|max:2000')]
    public string $note = '';

    /** @var list<string> */
    public array $notices = [];

    public function mount(Cart $cart): mixed
    {
        if ($cart->isEmpty()) {
            return $this->redirectRoute('shop.cart');
        }

        $this->notices = $cart->refresh();

        return null;
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

            return;
        } catch (EmptyCartException) {
            session()->flash('status', 'Košík je prázdný – objednávka už byla odeslána, nebo jste z něj vše odebrali.');
            $this->redirectRoute('shop.cart');

            return;
        }

        $this->redirect(URL::signedRoute('shop.order.confirmation', $order));
    }

    public function render(Cart $cart): View
    {
        return view('livewire.checkout-page', [
            'lines' => $cart->lines(),
            'total' => $cart->total(),
        ]);
    }
}
