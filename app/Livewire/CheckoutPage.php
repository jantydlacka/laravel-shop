<?php

namespace App\Livewire;

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Data\CheckoutData;
use App\Data\CheckoutRules;
use App\Exceptions\CartChangedException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\OrderAlreadyPlacedException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('Pokladna')]
class CheckoutPage extends Component
{
    // Bare #[Validate] keeps realtime validation on every property update; the rules come from rules().
    #[Validate]
    public string $name = '';

    #[Validate]
    public string $email = '';

    #[Validate]
    public string $phone = '';

    #[Validate]
    public string $street = '';

    #[Validate]
    public string $city = '';

    #[Validate]
    public string $zip = '';

    #[Validate]
    public string $note = '';

    /** @var list<string> */
    public array $notices = [];

    /** The cart as last shown in the summary; set on every render. */
    #[Locked]
    public string $cartFingerprint = '';

    /**
     * @return array<string, list<string>|string>
     */
    protected function rules(): array
    {
        return CheckoutRules::rules();
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return CheckoutRules::messages();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return CheckoutRules::attributes();
    }

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

        // Throttled here, not on the route: the form posts to Livewire's own endpoint.
        $limiterKey = 'place-order:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('shop.orders_per_hour'))) {
            $this->addError('submit', 'Z vaší adresy přišlo v poslední hodině příliš mnoho objednávek. Zkuste to prosím později.');

            return;
        }

        try {
            $order = $placeOrder->handle($cart, new CheckoutData(
                name: $this->name,
                email: $this->email,
                phone: $this->phone,
                street: $this->street,
                city: $this->city,
                zip: $this->zip,
                note: $this->note !== '' ? $this->note : null,
            ), $this->cartFingerprint);
        } catch (CartChangedException $e) {
            $this->notices = $e->messages;

            if ($cart->isEmpty()) {
                $this->backToCart($e->messages);
            }

            return;
        } catch (OrderAlreadyPlacedException $e) {
            session()->put('checkout.last_order', $e->order->number);
            $this->backToCart(["Objednávka č. {$e->order->number} už byla odeslána, košík je prázdný."]);

            return;
        } catch (EmptyCartException) {
            session()->flash('status', 'Košík je prázdný – objednávka už byla odeslána, nebo jste z něj vše odebrali.');
            $this->redirectRoute('shop.cart');

            return;
        }

        RateLimiter::hit($limiterKey, 3600);
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
        $this->cartFingerprint = $cart->fingerprint();

        return view('livewire.checkout-page', [
            'lines' => $cart->lines(),
            'total' => $cart->total(),
        ]);
    }
}
