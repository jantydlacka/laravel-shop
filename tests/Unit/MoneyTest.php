<?php

use App\Support\Money;

function plain(string $formatted): string
{
    return str_replace("\u{a0}", ' ', $formatted);
}

it('formats whole crowns without decimals', function () {
    expect(plain(Money::format(129900)))->toBe('1 299 Kč');
});

it('formats crowns with hellers', function () {
    expect(plain(Money::format(123450)))->toBe('1 234,50 Kč');
});

it('formats zero', function () {
    expect(plain(Money::format(0)))->toBe('0 Kč');
});

it('keeps a leading zero in hellers', function () {
    expect(plain(Money::format(1005)))->toBe('10,05 Kč');
});
