<?php

namespace App\Http\Resources;

use App\Models\Guardian;
use App\Models\Student;
use App\Support\SignedFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'admission_no' => $this->admission_no,
            'admission_date' => $this->admission_date?->toDateString(),
            'name' => $this->name,
            'dob' => $this->dob?->toDateString(),
            'gender' => $this->gender?->value,
            'blood_group' => $this->blood_group?->value,
            'father_name' => $this->father_name,
            'mother_name' => $this->mother_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'previous_school' => $this->previous_school,
            // Expiring signed link; the version busts caches when the photo changes.
            'photo_url' => $this->photo_path === null ? null : SignedFileUrl::to('files.student-photo', [
                'student' => $this->id,
                'v' => substr(sha1($this->photo_path), 0, 8),
            ]),
            'primary_guardian' => $this->whenLoaded(
                'primaryGuardian',
                fn () => ($g = $this->primaryGuardian->first()) ? $this->guardianLink($g) : null,
            ),
            'guardians' => $this->whenLoaded(
                'guardians',
                fn () => $this->guardians
                    ->sortByDesc(fn (Guardian $g) => (bool) $g->pivot->is_primary)
                    ->values()
                    ->map(fn (Guardian $g) => $this->guardianLink($g)),
            ),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'archived_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianLink(Guardian $guardian): array
    {
        return [
            'id' => $guardian->id,
            'name' => $guardian->name,
            'phone' => $guardian->phone,
            'relationship' => $guardian->pivot->relationship,
            'is_primary' => (bool) $guardian->pivot->is_primary,
        ];
    }
}
