<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subject line of a result: the paper's marks and grade, snapshotted
 * at calculation (subject name, full/pass marks, grade and point).
 */
class ResultDetail extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'full_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
            'cq' => 'decimal:2',
            'mcq' => 'decimal:2',
            'practical' => 'decimal:2',
            'obtained' => 'decimal:2',
            'absent' => 'boolean',
            'percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'passed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Result, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    /**
     * @return BelongsTo<ExamSubject, $this>
     */
    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }
}
