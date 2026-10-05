<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grading scheme: bands that each start at a percentage cut-off and run
 * up to the next one. Written by GradeScaleService, which keeps exactly one
 * band starting at 0 so every mark gets a grade.
 */
class GradeScale extends Model
{
    protected $guarded = ['id'];

    /**
     * Highest band first.
     *
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderByDesc('min_percent');
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /** The band a percentage (0–100) falls into. */
    public function gradeFor(float $percent): ?Grade
    {
        return $this->grades->first(fn (Grade $grade) => $percent >= (float) $grade->min_percent);
    }
}
