<?php

namespace App\Http\Requests\Api;

use App\Data\CheckoutData;
use App\Data\CheckoutRules;

class PlaceOrderRequest extends CartItemsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cart_token' => ['required', 'uuid'],
            ...parent::rules(),
            'customer' => ['required', 'array'],
            ...CheckoutRules::rules('customer.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), ...CheckoutRules::messages('customer.')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CheckoutRules::attributes('customer.');
    }

    public function cartToken(): string
    {
        return (string) $this->validated('cart_token');
    }

    public function toCheckoutData(): CheckoutData
    {
        /** @var array<string, string|null> $customer */
        $customer = $this->validated('customer');

        return new CheckoutData(
            name: (string) $customer['name'],
            email: (string) $customer['email'],
            phone: (string) $customer['phone'],
            street: (string) $customer['street'],
            city: (string) $customer['city'],
            zip: (string) $customer['zip'],
            note: ($customer['note'] ?? null) !== null && $customer['note'] !== '' ? $customer['note'] : null,
        );
    }
}
