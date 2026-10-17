<?php

namespace App\Http\Controllers;

use App\Actions\Certificate\IssueTransferCertificate;
use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\StudentEnrollment;
use App\Support\SchoolLetterhead;
use Illuminate\Http\JsonResponse;

/**
 * Transfer certificates (spec §9). Issuing needs enrollment.edit; reading
 * an issued one needs enrollment.view. Only transferred enrollments qualify.
 */
class TransferCertificateController extends Controller
{
    public function show(StudentEnrollment $enrollment): JsonResponse
    {
        $certificate = Certificate::query()
            ->where('type', CertificateType::Transfer->value)
            ->where('student_enrollment_id', $enrollment->id)
            ->first();
        abort_if($certificate === null, 404, 'No transfer certificate has been issued for this enrollment yet.');

        return $this->view($certificate, $enrollment);
    }

    public function store(StudentEnrollment $enrollment, IssueTransferCertificate $issue): JsonResponse
    {
        $data = request()->validate(['conduct' => ['nullable', 'string', 'max:100']]);
        $conduct = isset($data['conduct']) && trim($data['conduct']) !== '' ? trim($data['conduct']) : null;

        $certificate = $issue->execute($enrollment, request()->user(), $conduct);

        return $this->view($certificate, $enrollment)->setStatusCode($certificate->wasRecentlyCreated ? 201 : 200);
    }

    private function view(Certificate $certificate, StudentEnrollment $enrollment): JsonResponse
    {
        $enrollment->loadMissing(['student', 'academicYear', 'schoolClass', 'section']);
        $certificate->loadMissing('issuer');
        $student = $enrollment->student;

        return response()->json(['data' => [
            'id' => $certificate->id,
            'serial_no' => $certificate->serial_no,
            'issued_on' => $certificate->issued_on->toDateString(),
            'issued_by' => $certificate->issuer?->name,
            'conduct' => $certificate->conduct,
            'school' => SchoolLetterhead::toArray(),
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'dob' => $student->dob->toDateString(),
                'gender' => $student->gender->value,
                'student_id' => $student->student_id,
                'admission_no' => $student->admission_no,
                'admission_date' => $student->admission_date->toDateString(),
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'academic_year' => $enrollment->academicYear->name,
                'class' => $enrollment->schoolClass->name,
                'section' => $enrollment->section->name,
                'roll' => $enrollment->roll,
                'left_on' => $enrollment->ended_on?->toDateString(),
                'reason' => $enrollment->exit_reason,
            ],
        ]]);
    }
}
