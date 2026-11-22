<?php

namespace App\Data;

/**
 * Validation of the customer fields; shared by the checkout page and the JSON API.
 */
final class CheckoutRules
{
    /**
     * @return array<string, list<string>|string>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}name" => 'required|string|max:255',
            "{$prefix}email" => 'required|email|max:255',
            "{$prefix}phone" => 'required|string|max:30',
            "{$prefix}street" => 'required|string|max:255',
            "{$prefix}city" => 'required|string|max:255',
            "{$prefix}zip" => ['required', 'regex:/^\d{3}\s?\d{2}$/'],
            "{$prefix}note" => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}name.required" => 'Vyplňte jméno a příjmení.',
            "{$prefix}email.required" => 'Vyplňte e-mail.',
            "{$prefix}email.email" => 'Zadejte platný e-mail.',
            "{$prefix}phone.required" => 'Vyplňte telefon.',
            "{$prefix}street.required" => 'Vyplňte ulici a číslo domu.',
            "{$prefix}city.required" => 'Vyplňte město.',
            "{$prefix}zip.required" => 'Vyplňte PSČ.',
            "{$prefix}zip.regex" => 'PSČ má pět číslic, např. 110 00.',
            "{$prefix}note.max" => 'Poznámka může mít nejvýš 2000 znaků.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $prefix = ''): array
    {
        return [
            "{$prefix}name" => 'jméno a příjmení',
            "{$prefix}email" => 'e-mail',
            "{$prefix}phone" => 'telefon',
            "{$prefix}street" => 'ulice a číslo domu',
            "{$prefix}city" => 'město',
            "{$prefix}zip" => 'PSČ',
            "{$prefix}note" => 'poznámka',
        ];
    }
}
