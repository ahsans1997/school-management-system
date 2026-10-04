<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subject taught in a class (row of the class_subjects pivot). A model of
 * its own because later modules hang data on it — the subject teacher
 * arrives in Module 12.
 */
class ClassSubject extends Model
{
    protected $table = 'class_subjects';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
