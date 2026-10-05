<?php

namespace App\Http\Resources;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Exam */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name]),
            'grade_scale' => $this->whenLoaded('gradeScale', fn () => ['id' => $this->gradeScale->id, 'name' => $this->gradeScale->name]),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'papers_count' => $this->whenCounted('papers'),
            'classes_count' => $this->whenCounted('classes'),
            'archived' => $this->trashed(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
