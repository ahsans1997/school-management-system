<?php

namespace App\Enums;

/** Kinds of exam (spec §16). */
enum ExamType: string
{
    case Monthly = 'monthly';
    case HalfYearly = 'half_yearly';
    case Annual = 'annual';
    case Model = 'model';
    case Test = 'test';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly test',
            self::HalfYearly => 'Half yearly',
            self::Annual => 'Annual',
            self::Model => 'Model test',
            self::Test => 'Test exam',
            self::Custom => 'Custom',
        };
    }
}
