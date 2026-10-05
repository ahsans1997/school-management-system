<?php

namespace App\Http\Resources;

use App\Models\ExamSubject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One paper. Marks come out as numbers (they're decimals of at most two
 * places, not money); a null section means every section of the class.
 *
 * @mixin ExamSubject
 */
class ExamSubjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $number = fn ($value) => $value === null ? null : (float) $value;

        return [
            'id' => $this->id,
            'class_subject_id' => $this->class_subject_id,
            'class' => $this->whenLoaded('schoolClass', fn () => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name]),
            'section' => $this->whenLoaded('section', fn () => $this->section ? ['id' => $this->section->id, 'name' => $this->section->name] : null),
            'subject' => $this->whenLoaded('classSubject', fn () => [
                'id' => $this->classSubject->subject->id,
                'name' => $this->classSubject->subject->name,
                'code' => $this->classSubject->subject->code,
            ]),
            'full_marks' => $number($this->full_marks),
            'pass_marks' => $number($this->pass_marks),
            'cq_marks' => $number($this->cq_marks),
            'mcq_marks' => $number($this->mcq_marks),
            'practical_marks' => $number($this->practical_marks),
            'exam_date' => $this->exam_date,
            'start_time' => $this->startsAt(),
            'end_time' => $this->endsAt(),
        ];
    }
}
