<?php

namespace App\Support;

final class Vat
{
    /**
     * VAT contained in a gross (VAT-inclusive) amount, rounded half-up to whole hellers.
     * Integer arithmetic: round(gross * rate / (100 + rate)).
     */
    public static function fromGross(int $gross, int $ratePercent): int
    {
        $divisor = 100 + $ratePercent;

        return intdiv(2 * $gross * $ratePercent + $divisor, 2 * $divisor);
    }
}
