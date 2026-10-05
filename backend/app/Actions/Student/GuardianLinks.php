<?php

namespace App\Actions\Student;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links between a student and their guardians. Invariant: a student with
 * any guardians has exactly one primary. The database guarantees "at most
 * one" (unique generated column); these methods keep "at least one".
 * Each runs in a transaction holding the student's row lock, so concurrent
 * changes to the same student queue up.
 */
class GuardianLinks
{
    /** The first guardian linked becomes primary, whatever was asked. */
    public function link(Student $student, Guardian $guardian, string $relationship, bool $makePrimary): void
    {
        $this->locked($student, function () use ($student, $guardian, $relationship, $makePrimary) {
            $primary = $makePrimary || ! $this->links($student)->where('is_primary', true)->exists();

            if ($primary) {
                $this->clearPrimary($student);
            }

            $student->guardians()->attach($guardian->id, [
                'relationship' => $relationship,
                'is_primary' => $primary,
            ]);
        });
    }

    /** `$isPrimary`: true promotes this guardian; false is refused for the current primary. */
    public function update(Student $student, Guardian $guardian, string $relationship, ?bool $isPrimary): void
    {
        $this->locked($student, function () use ($student, $guardian, $relationship, $isPrimary) {
            $link = $this->links($student)->where('guardian_id', $guardian->id)->first();
            abort_if($link === null, 404);

            if ($isPrimary === false && $link->is_primary) {
                throw ValidationException::withMessages([
                    'is_primary' => 'A student always has one primary guardian. Make another guardian primary instead.',
                ]);
            }

            if ($isPrimary === true && ! $link->is_primary) {
                $this->clearPrimary($student);
            }

            $this->links($student)->where('guardian_id', $guardian->id)->update([
                'relationship' => $relationship,
                'is_primary' => $isPrimary === true || $link->is_primary,
                'updated_at' => now(),
            ]);
        });
    }

    /** Unlinking the primary promotes the longest-linked remaining guardian. */
    public function unlink(Student $student, Guardian $guardian): void
    {
        $this->locked($student, function () use ($student, $guardian) {
            $link = $this->links($student)->where('guardian_id', $guardian->id)->first();
            abort_if($link === null, 404);

            $this->links($student)->where('guardian_id', $guardian->id)->delete();

            $successor = $link->is_primary ? $this->links($student)->orderBy('id')->value('id') : null;
            if ($successor !== null) {
                DB::table('student_guardians')->where('id', $successor)->update(['is_primary' => true]);
            }
        });
    }

    private function locked(Student $student, callable $callback): void
    {
        DB::transaction(function () use ($student, $callback) {
            Student::query()->whereKey($student->id)->lockForUpdate()->first();
            $callback();
        });
    }

    private function links(Student $student): Builder
    {
        return DB::table('student_guardians')->where('student_id', $student->id);
    }

    private function clearPrimary(Student $student): void
    {
        $this->links($student)->where('is_primary', true)->update(['is_primary' => false]);
    }
}
