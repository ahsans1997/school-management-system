<?php

namespace App\Models;

use App\Models\Contracts\HasAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A parent/guardian (spec §20). Stored under the morph alias "guardian"
 * (AppServiceProvider), matching User::PERSON_TYPES.
 *
 * @property-read StudentGuardian $pivot  the student link, when loaded through Student::guardians()
 */
class Guardian extends Model implements HasAccount
{
    protected $guarded = ['id'];

    /**
     * The guardian's Parent-portal login, if one was created.
     *
     * @return MorphOne<User, $this>
     */
    public function account(): MorphOne
    {
        return $this->morphOne(User::class, 'personable');
    }

    /**
     * The guardian's children. Archived students drop out (soft deletes).
     *
     * @return BelongsToMany<Student, $this, StudentGuardian>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->using(StudentGuardian::class)
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }
}
