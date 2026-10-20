<?php

namespace App\Models;

use App\Enums\MarkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's marks on one paper. Written only by App\Actions\Exam\EnterMarks
 * (which computes `total`) and SubmitMarks (status). Internal: no public
 * serializer — results (Module 18) are what gets published.
 */
class Mark extends Model
{
    /**
     * Only the values EnterMarks computes. Links, status and who-did-what
     * are set with forceFill / direct assignment by the actions.
     */
    protected $fillable = ['cq', 'mcq', 'practical', 'total', 'absent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cq' => 'decimal:2',
            'mcq' => 'decimal:2',
            'practical' => 'decimal:2',
            'total' => 'decimal:2',
            'absent' => 'boolean',
            'status' => MarkStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamSubject, $this>
     */
    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isLocked(): bool
    {
        return $this->status === MarkStatus::Submitted;
    }
}
