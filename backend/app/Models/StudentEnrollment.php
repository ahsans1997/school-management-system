<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place for one academic year (section, class, roll, status).
 * `class_id` always mirrors the section's class — set by the actions, never
 * by the client, and checked by a composite FK.
 */
class StudentEnrollment extends Model
{
    protected $guarded = ['id', 'class_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roll' => 'integer',
            'status' => EnrollmentStatus::class,
            'ended_on' => 'date',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EnrollmentStatus::Active->value);
    }

    /**
     * A section's roll on a day: enrollments for that year still running on
     * $date (active, or ended after it), of students who aren't archived.
     * Ordered by roll. Used by attendance sheets and marks sheets.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOnRoll(Builder $query, Section $section, AcademicYear $year, string $date): void
    {
        $query->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->where(fn ($q) => $q->where('status', EnrollmentStatus::Active->value)->orWhereDate('ended_on', '>', $date))
            ->whereHas('student', fn ($q) => $q->withoutTrashed())
            ->orderBy('roll');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
