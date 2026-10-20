<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Where a fee invoice stands. Derived from paid vs total by whoever changes
 * `paid` (Payments, Module 20); void is set only by voiding.
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Partial => 'Partly paid',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    /**
     * The status for amounts in hundredths (never for a void invoice).
     * Paying more than the total, or a negative amount, is a bug upstream.
     */
    public static function for(int $totalCents, int $paidCents): self
    {
        if ($paidCents < 0 || $paidCents > $totalCents) {
            throw new InvalidArgumentException("Paid ({$paidCents}) must be between 0 and the total ({$totalCents}).");
        }

        return match (true) {
            $paidCents <= 0 => self::Unpaid,
            $paidCents >= $totalCents => self::Paid,
            default => self::Partial,
        };
    }
}
