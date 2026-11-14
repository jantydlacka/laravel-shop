<?php

namespace App\Data;

final readonly class CheckoutData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $street,
        public string $city,
        public string $zip,
        public ?string $note,
    ) {}
}
