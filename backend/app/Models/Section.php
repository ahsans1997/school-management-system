<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A division of a class (e.g. "A"). Its class is fixed at creation; capacity
 * caps enrollment (checked in Module 13).
 */
class Section extends Model
{
    protected $guarded = ['id', 'class_id', 'class_teacher_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Set through the teacher's class-teacher endpoints, never mass-assigned.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }
}
