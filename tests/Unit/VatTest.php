<?php

use App\Support\Vat;

it('extracts VAT from a gross amount, rounded half-up to whole hellers', function (int $gross, int $vat) {
    expect(Vat::fromGross($gross, 21))->toBe($vat);
})->with([
    'exact' => [12100, 2100],
    'rounds down (17.355)' => [100, 17],
    'zero' => [0, 0],
    'rounds up (0.520…)' => [3, 1],
]);

it('works with another rate', function () {
    expect(Vat::fromGross(11200, 12))->toBe(1200);
});

it('rounds an exact half up', function () {
    // 14 * 12 / 112 = 1.5 (an exact half cannot occur at 21 %).
    expect(Vat::fromGross(14, 12))->toBe(2);
});
