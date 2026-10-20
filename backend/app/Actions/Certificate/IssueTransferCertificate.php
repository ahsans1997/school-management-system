<?php

namespace App\Actions\Certificate;

use App\Enums\CertificateType;
use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\SchoolClock;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Issue the transfer certificate for an enrollment that ended in a transfer.
 * Issuing again returns the same certificate (serial and issue date are
 * fixed); only the conduct remark can be corrected.
 */
class IssueTransferCertificate
{
    public function execute(StudentEnrollment $enrollment, User $issuer, ?string $conduct): Certificate
    {
        abort_unless(
            $enrollment->status === EnrollmentStatus::Transferred,
            422,
            'A transfer certificate can only be issued once the enrollment is marked as transferred.',
        );

        return DB::transaction(function () use ($enrollment, $issuer, $conduct) {
            // Serialize issuing for this enrollment so two clicks can't make two certificates.
            StudentEnrollment::query()->whereKey($enrollment->id)->lockForUpdate()->first();

            $certificate = Certificate::query()
                ->where('type', CertificateType::Transfer->value)
                ->where('student_enrollment_id', $enrollment->id)
                ->first();

            if ($certificate !== null) {
                if ($conduct !== null && $conduct !== $certificate->conduct) {
                    $certificate->update(['conduct' => $conduct]);
                }

                return $certificate;
            }

            $year = (int) SchoolClock::today()->format('Y');

            return Certificate::create([
                'type' => CertificateType::Transfer,
                'serial_no' => sprintf('%s-%d-%04d', CertificateType::Transfer->prefix(), $year, Sequence::next("certificate.transfer.{$year}")),
                'student_id' => $enrollment->student_id,
                'student_enrollment_id' => $enrollment->id,
                'issued_on' => SchoolClock::today(),
                'issued_by' => $issuer->id,
                'conduct' => $conduct ?? 'Good',
            ]);
        });
    }
}
