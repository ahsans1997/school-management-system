<?php

namespace App\Http\Resources;

use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentEnrollment */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'student_id' => $this->student->student_id,
                'archived' => $this->student->trashed(),
            ]),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_current' => $this->academicYear->is_current,
            ]),
            'class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
            ]),
            'section' => $this->whenLoaded('section', fn () => [
                'id' => $this->section->id,
                'name' => $this->section->name,
            ]),
            'roll' => $this->roll,
            'status' => $this->status->value,
            'ended_on' => $this->ended_on?->toDateString(),
            'exit_reason' => $this->exit_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
