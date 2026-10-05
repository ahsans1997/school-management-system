<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Services\Student\StudentNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateStudentAction
{
    public function __construct(private readonly StudentNumberGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  validated StudentRequest fields
     */
    public function execute(array $data): Student
    {
        // One transaction, so a failed save hands its admission number back.
        return DB::transaction(function () use ($data) {
            $admissionDate = isset($data['admission_date'])
                ? Carbon::parse($data['admission_date'])
                : today();

            return Student::create([
                ...$data,
                'admission_date' => $admissionDate,
                'admission_no' => $data['admission_no'] ?? $this->numbers->admissionNo($admissionDate->year),
                'student_id' => $data['student_id'] ?? $this->numbers->studentId(),
            ]);
        });
    }
}
