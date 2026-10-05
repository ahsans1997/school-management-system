<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One band of a grade scale, e.g. A+ = 5.00 from 80 %. */
class Grade extends Model
{
    protected $guarded = ['id', 'grade_scale_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade_point' => 'decimal:2',
            'min_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<GradeScale, $this>
     */
    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }
}
