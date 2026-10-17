<?php

namespace App\Models;

use App\Enums\CertificateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued certificate. Its serial number and issue date never change, so
 * reprints match the original. Created by IssueTransferCertificate.
 */
class Certificate extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'issued_on' => 'date',
        ];
    }

    /**
     * Archived students keep their certificates.
     *
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
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
