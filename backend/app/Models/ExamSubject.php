<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** The parts a paper can be split into (a mark's column => the paper's cap column), in order. */
    public const PARTS = ['cq' => 'cq_marks', 'mcq' => 'mcq_marks', 'practical' => 'practical_marks'];

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
     * Students' marks on this paper (Module 17).
     *
     * @return HasMany<Mark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    /**
     * The parts this paper is split into, part => cap (e.g. ['cq' => '70.00',
     * 'mcq' => '30.00']); empty when it's marked as a single figure.
     *
     * @return array<string, string>
     */
    public function parts(): array
    {
        $parts = [];
        foreach (self::PARTS as $part => $column) {
            if ($this->{$column} !== null) {
                $parts[$part] = $this->{$column};
            }
        }

        return $parts;
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
