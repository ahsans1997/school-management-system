<?php

namespace App\Enums;

/**
 * Where a mark is in the results workflow (spec §56). Teachers enter
 * drafts; submitting a sheet locks it for results (Module 18) until an
 * exam admin reopens it.
 */
enum MarkStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
        };
    }
}
