<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Models\Concerns\HasDocuments;
use App\Models\Contracts\HasPrivateFiles;
use App\Support\TeacherScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The student master record (spec §9). Deleting archives (soft delete).
 * Class/section/roll for a year live on the enrollment (Module 13).
 *
 * @property-read StudentGuardian $pivot  the guardian link, when loaded through Guardian::students()
 */
class Student extends Model implements HasPrivateFiles
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
     * @return BelongsToMany<Guardian, $this, StudentGuardian>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->using(StudentGuardian::class)
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * The primary guardian (at most one — enforced by a unique index).
     *
     * @return BelongsToMany<Guardian, $this, StudentGuardian>
     */
    public function primaryGuardian(): BelongsToMany
    {
        return $this->guardians()->wherePivot('is_primary', true);
    }

    /**
     * Every year's enrollment, newest year first.
     *
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)
            ->orderByDesc(AcademicYear::select('start_date')->whereColumn('academic_years.id', 'student_enrollments.academic_year_id'));
    }

    /**
     * The enrollment for the current academic year, if any (whatever its status).
     *
     * @return HasOne<StudentEnrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)
            ->where('academic_year_id', AcademicYear::current()->id ?? 0);
    }

    /**
     * Ownership scope (spec §52): a teacher sees only students actively
     * enrolled this year in a section they teach or are class teacher of.
     * Everyone else allowed past the permission check sees all students.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $sectionIds = TeacherScope::sectionIds($user);
        if ($sectionIds === null) {
            return;
        }

        $query->whereHas('enrollments', fn (Builder $q) => $q->active()
            ->where('academic_year_id', AcademicYear::current()->id ?? 0)
            ->whereIn('section_id', $sectionIds));
    }
}
