<?php

namespace App\Models;

use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The teacher master record (spec §11). Stored under the morph alias
 * "teacher". Deleting archives (soft delete).
 */
class Teacher extends Model
{
    use HasDocuments, SoftDeletes;

    /** photo_path is set only through the photo actions. */
    protected $guarded = ['id', 'photo_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    /**
     * The teacher's login (Teacher role), if one was created.
     *
     * @return MorphOne<User, $this>
     */
    public function account(): MorphOne
    {
        return $this->morphOne(User::class, 'personable');
    }

    /**
     * Subjects taught, per section.
     *
     * @return HasMany<TeachingAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /**
     * Sections this teacher is class teacher of.
     *
     * @return HasMany<Section, $this>
     */
    public function classTeacherOf(): HasMany
    {
        return $this->hasMany(Section::class, 'class_teacher_id');
    }
}
