<?php

namespace App\Support;

/**
 * Money is stored as integer hellers (1 CZK = 100 hellers).
 */
final class Money
{
    public static function format(int $hellers): string
    {
        $decimals = $hellers % 100 === 0 ? 0 : 2;

        return number_format($hellers / 100, $decimals, ',', "\u{a0}")."\u{a0}Kč";
    }
}
