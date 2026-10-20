<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money maths (spec §53): amounts become integer hundredths (paisa)
 * for adding and comparing, and go back to "1500.00" strings for DECIMAL
 * columns and the API. Never add money as floats.
 */
final class Money
{
    /** "1500.5" / 1500.5 / "1,500.50"-free decimals → 150050. Null or "" → 0. */
    public static function cents(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $text = is_string($value) ? trim($value) : number_format((float) $value, 2, '.', '');
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $text, $m)) {
            throw new InvalidArgumentException("Not a money amount: {$text}");
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** 150050 → "1500.50" */
    public static function decimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /** Normalise any amount to "1500.50" (null stays null). */
    public static function format(int|float|string|null $value): ?string
    {
        return $value === null ? null : self::decimal(self::cents($value));
    }
}
