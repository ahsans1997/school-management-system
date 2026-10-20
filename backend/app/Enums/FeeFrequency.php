<?php

namespace App\Enums;

/**
 * When a class's fee is billed on its monthly invoices:
 *  - monthly:   on every month's invoice (tuition);
 *  - one_time:  once per academic year, on the first invoice that includes
 *               it (admission or session fee);
 *  - exam:      only on the invoices it's chosen for (exam fees).
 */
enum FeeFrequency: string
{
    case Monthly = 'monthly';
    case OneTime = 'one_time';
    case Exam = 'exam';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Every month',
            self::OneTime => 'Once a year',
            self::Exam => 'When chosen (exam)',
        };
    }
}
