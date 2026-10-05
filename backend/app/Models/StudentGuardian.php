<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A student ↔ guardian link (the student_guardians pivot): how they're
 * related and whether this is the primary guardian. Read through `pivot`
 * on Student::guardians() and Guardian::students().
 */
class StudentGuardian extends Pivot
{
    protected $table = 'student_guardians';

    /** The table has its own auto-increment id. */
    public $incrementing = true;

    /** `primary_marker` is a generated column — never written by the app. */
    protected $guarded = ['id', 'primary_marker'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
