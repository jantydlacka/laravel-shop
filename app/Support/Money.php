<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer hellers (1 CZK = 100 hellers).
 */
final class Money
{
    public const CROWNS_PATTERN = '/^\d+([.,]\d{1,2})?$/';

    public static function format(int $hellers): string
    {
        $decimals = $hellers % 100 === 0 ? 0 : 2;

        return number_format($hellers / 100, $decimals, ',', "\u{a0}")."\u{a0}Kč";
    }

    /**
     * Parses "1299,50" / "1299.5" / "1299" into hellers by string splitting, never through a float.
     */
    public static function parseCrowns(string $crowns): int
    {
        $crowns = trim($crowns);

        if (preg_match(self::CROWNS_PATTERN, $crowns) !== 1) {
            throw new InvalidArgumentException("Invalid crown amount [{$crowns}].");
        }

        [$whole, $fraction] = array_pad(preg_split('/[.,]/', $crowns), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Editable form of an amount: "1299,50", or "1299" for whole crowns.
     */
    public static function toCrowns(int $hellers): string
    {
        $crowns = intdiv($hellers, 100);
        $rest = $hellers % 100;

        return $rest === 0 ? (string) $crowns : sprintf('%d,%02d', $crowns, $rest);
    }
}
