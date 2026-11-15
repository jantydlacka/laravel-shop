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

it('parses a crown amount into hellers without floats', function (string $input, int $hellers) {
    expect(Money::parseCrowns($input))->toBe($hellers);
})->with([
    ['1299,50', 129950],
    ['1299.5', 129950],
    ['1299', 129900],
    ['0,01', 1],
    [' 15,9 ', 1590],
]);

it('rejects malformed crown amounts', function (string $input) {
    Money::parseCrowns($input);
})->with(['-1', 'abc', '12,345', '', '1 299', 'too many digits' => '99999999999999999'])->throws(InvalidArgumentException::class);

it('renders hellers as an editable crown amount', function (int $hellers, string $crowns) {
    expect(Money::toCrowns($hellers))->toBe($crowns);
})->with([
    [129950, '1299,50'],
    [129900, '1299'],
    [1, '0,01'],
]);
