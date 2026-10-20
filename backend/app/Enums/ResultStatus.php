<?php

namespace App\Enums;

/**
 * The result publishing workflow (spec §56). A class's results move
 * together: draft → review → approved → published. Only published results
 * reach students, parents and (Module 32) the public site.
 */
enum ResultStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'In review',
            self::Approved => 'Approved',
            self::Published => 'Published',
        };
    }
}
