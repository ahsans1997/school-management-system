<?php

namespace App\Http\Controllers;

use App\Actions\Student\ImportStudents;
use App\Enums\EnrollmentStatus;
use App\Http\Requests\Student\ImportStudentsRequest;
use App\Models\AcademicYear;
use App\Services\Student\StudentExport;
use App\Support\Spreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Moving student records in and out as CSV/Excel (spec §9): the import
 * template, the import itself (student.create; enrollment.create too when
 * rows carry a class), and the export (student.export).
 */
class StudentImportExportController extends Controller
{
    /** An empty sheet with the import's columns. */
    public function template(): BinaryFileResponse
    {
        $format = $this->format();
        $header = ['Student ID', 'Admission no', 'Name', 'Gender', 'Date of birth', 'Blood group', 'Phone', 'Address',
            "Father's name", "Mother's name", 'Admission date', 'Previous school', 'Class', 'Section', 'Roll'];

        return $this->download(Spreadsheet::write($format, $header, []), "student-import-template.{$format}", $format);
    }

    public function import(ImportStudentsRequest $request, ImportStudents $import): JsonResponse
    {
        $result = $import->execute($request->file('file'), $request->user(), $request->boolean('dry_run'));

        return response()->json(['data' => $result], $result['dry_run'] ? 200 : 201);
    }

    public function export(StudentExport $export): BinaryFileResponse
    {
        $filters = request()->validate([
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...array_column(EnrollmentStatus::cases(), 'value'), 'not_enrolled'])],
            'archived' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $format = $this->format();
        $year = isset($filters['academic_year_id']) ? AcademicYear::findOrFail($filters['academic_year_id']) : AcademicYear::current();
        abort_if($year === null, 422, 'There is no current academic year.');

        $rows = $export->rows(request()->user(), $year, [
            'class_id' => $filters['class_id'] ?? null,
            'section_id' => $filters['section_id'] ?? null,
            'status' => $filters['status'] ?? null,
            'archived' => (bool) ($filters['archived'] ?? false),
            'search' => $filters['search'] ?? null,
        ]);
        $name = 'students-'.Str::slug($year->name).'.'.$format;

        return $this->download(Spreadsheet::write($format, StudentExport::HEADER, $rows), $name, $format);
    }

    private function format(): string
    {
        request()->validate(['format' => ['nullable', Rule::in(Spreadsheet::FORMATS)]]);

        return request()->input('format', 'csv');
    }

    private function download(string $path, string $name, string $format): BinaryFileResponse
    {
        return response()->download($path, $name, [
            'Content-Type' => Spreadsheet::MIME_TYPES[$format],
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend();
    }
}
