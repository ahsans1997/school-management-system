<?php

namespace App\Models;

use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's result in an exam, calculated by App\Actions\Exam\CalculateResults
 * from submitted marks and moved through the workflow by ResultWorkflow.
 * Nothing here is mass-assignable from input.
 */
class Result extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'full_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'gpa' => 'decimal:2',
            'passed' => 'boolean',
            'failed_subjects' => 'integer',
            'position' => 'integer',
            'class_position' => 'integer',
            'status' => ResultStatus::class,
            'calculated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ResultStatus::Published->value);
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
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

    /**
     * Per-subject lines, in the order they were calculated (paper order).
     *
     * @return HasMany<ResultDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(ResultDetail::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
