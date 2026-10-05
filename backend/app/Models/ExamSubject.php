<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One paper of an exam: a class subject with its full/pass marks, the
 * optional CQ/MCQ/practical split, and when it's sat. A NULL section means
 * every section of the class. Written by SaveExamSubjects.
 */
class ExamSubject extends Model
{
    protected $table = 'exam_subjects';

    /** `section_key` is a generated column — never written by the app. */
    protected $guarded = ['id', 'section_key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'full_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
            'cq_marks' => 'decimal:2',
            'mcq_marks' => 'decimal:2',
            'practical_marks' => 'decimal:2',
            // exam_date stays a plain "YYYY-MM-DD" string (no date cast), so
            // it's stored and compared the same way on every database.
        ];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Papers a section sits: its class's class-wide papers plus its own.
     *
     * @param  Builder<ExamSubject>  $query
     */
    public function scopeForSection(Builder $query, Section $section): void
    {
        $query->where('exam_subjects.class_id', $section->class_id)
            ->where(fn ($q) => $q->whereNull('exam_subjects.section_id')->orWhere('exam_subjects.section_id', $section->id));
    }

    /** "HH:MM" (the column may come back as "HH:MM:SS"). */
    public function startsAt(): ?string
    {
        return $this->start_time ? substr($this->start_time, 0, 5) : null;
    }

    public function endsAt(): ?string
    {
        return $this->end_time ? substr($this->end_time, 0, 5) : null;
    }
}
