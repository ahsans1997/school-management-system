<?php

namespace App\Enums;

/** Certificates the school issues. Testimonials need results (Module 18). */
enum CertificateType: string
{
    case Transfer = 'transfer';
    case Testimonial = 'testimonial';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer certificate',
            self::Testimonial => 'Testimonial',
        };
    }

    /** Serial prefix, e.g. "TC-2026-0001". */
    public function prefix(): string
    {
        return match ($this) {
            self::Transfer => 'TC',
            self::Testimonial => 'TM',
        };
    }
}
