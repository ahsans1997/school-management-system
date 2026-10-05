<?php

namespace App\Http\Resources;

use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Support\SignedFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Teacher */
class TeacherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'designation' => $this->designation,
            'department' => $this->department,
            'qualification' => $this->qualification,
            'joining_date' => $this->joining_date->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            // Decimal string ("25000.00"), never a float. Not for public profiles (Module 28).
            'salary' => $this->salary,
            'photo_url' => $this->photo_path === null ? null : SignedFileUrl::to('files.teacher-photo', [
                'teacher' => $this->id,
                'v' => substr(sha1($this->photo_path), 0, 8),
            ]),
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'email' => $this->account->email,
                'is_active' => $this->account->is_active,
            ] : null),
            'assignments_count' => $this->whenCounted('assignments'),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments
                ->sortBy(fn (TeachingAssignment $a) => [
                    $a->section->schoolClass->numeric_level, $a->section->name, $a->classSubject->subject->name,
                ])
                ->values()
                ->map(fn (TeachingAssignment $a) => [
                    'id' => $a->id,
                    'class' => ['id' => $a->section->schoolClass->id, 'name' => $a->section->schoolClass->name],
                    'section' => ['id' => $a->section->id, 'name' => $a->section->name],
                    'subject' => [
                        'id' => $a->classSubject->subject->id,
                        'name' => $a->classSubject->subject->name,
                        'code' => $a->classSubject->subject->code,
                    ],
                ])),
            'class_teacher_of' => $this->whenLoaded('classTeacherOf', fn () => $this->classTeacherOf
                ->map(fn (Section $s) => [
                    'class' => ['id' => $s->schoolClass->id, 'name' => $s->schoolClass->name],
                    'section' => ['id' => $s->id, 'name' => $s->name],
                ])),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'archived_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
