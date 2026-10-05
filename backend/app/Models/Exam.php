<?php

namespace App\Models;

use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An exam in an academic year (spec §16). Its year is fixed at creation;
 * deleting archives it (soft delete) so marks and results keep their exam.
 */
class Exam extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'academic_year_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<GradeScale, $this>
     */
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    /**
     * The exam's papers across all classes.
     *
     * @return HasMany<ExamSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    /** Last day of the exam: the end date, or the start date for a one-day exam. */
    public function lastDay(): Carbon
    {
        return $this->end_date ?? $this->start_date;
    }
}
