<?php

namespace App\Http\Resources;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guardian */
class GuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'occupation' => $this->occupation,
            'address' => $this->address,
            // The Parent login, if any — only what the screen needs.
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'email' => $this->account->email,
                'is_active' => $this->account->is_active,
            ] : null),
            'students_count' => $this->whenCounted('students'),
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'student_id' => $s->student_id,
                'relationship' => $s->pivot->relationship,
                'is_primary' => (bool) $s->pivot->is_primary,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
