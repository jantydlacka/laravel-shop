<?php

namespace App\Cart;

/**
 * Validation of a quantity typed by the customer; shared by the product page and the cart page.
 */
final class QuantityRules
{
    /**
     * @return list<string>
     */
    public static function rules(int $max): array
    {
        return ['required', 'integer', 'min:1', "max:{$max}"];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field): array
    {
        return [
            "{$field}.required" => 'Zadejte množství.',
            "{$field}.integer" => 'Množství musí být celé číslo.',
            "{$field}.min" => 'Množství musí být alespoň 1.',
            "{$field}.max" => 'Víc než :max ks objednat nejde.',
        ];
    }
}
