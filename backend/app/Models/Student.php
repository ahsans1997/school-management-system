<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The student master record (spec §9). Deleting archives (soft delete).
 * Class/section/roll for a year live on the enrollment (Module 13).
 */
class Student extends Model
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
            'admission_date' => 'date',
            'dob' => 'date',
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
        ];
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * The primary guardian (at most one — enforced by a unique index).
     *
     * @return BelongsToMany<Guardian, $this>
     */
    public function primaryGuardian(): BelongsToMany
    {
        return $this->guardians()->wherePivot('is_primary', true);
    }
}
