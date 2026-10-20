<?php

namespace App\Http\Resources;

use App\Models\Mark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's marks on one paper — internal only (marks entry screens).
 * There is deliberately no public serializer: results are published by
 * Module 18, never raw marks.
 *
 * @mixin Mark
 */
class MarkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $number = fn ($value) => $value === null ? null : (float) $value;

        return [
            'id' => $this->id,
            'exam_subject_id' => $this->exam_subject_id,
            'student_id' => $this->student_id,
            'cq' => $number($this->cq),
            'mcq' => $number($this->mcq),
            'practical' => $number($this->practical),
            'total' => $number($this->total),
            'absent' => $this->absent,
            'status' => $this->status->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
