<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Services\Student\IdCardService;
use App\Support\TeacherScope;
use Illuminate\Http\JsonResponse;

/**
 * Printable ID card data for one student or a whole section (student.view).
 * Teachers reach only their own sections' students.
 */
class IdCardController extends Controller
{
    public function __construct(private readonly IdCardService $cards) {}

    public function student(Student $student): JsonResponse
    {
        // Outside a teacher's sections the student doesn't exist for them.
        abort_unless(Student::query()->visibleTo(request()->user())->whereKey($student->id)->exists(), 404, 'Not found.');
        $year = $this->year();

        $sheet = $this->cards->forStudent($student, $year);
        abort_if($sheet === null, 422, "{$student->name} isn't enrolled for {$year->name}, so there's no ID card to print.");

        return response()->json(['data' => $sheet]);
    }

    public function section(Section $section): JsonResponse
    {
        TeacherScope::authorize(request()->user(), $section);

        return response()->json(['data' => [
            'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
            ...$this->cards->forSection($section, $this->year()),
        ]]);
    }

    private function year(): AcademicYear
    {
        request()->validate(['academic_year_id' => ['nullable', 'integer']]);
        $year = request()->integer('academic_year_id')
            ? AcademicYear::findOrFail(request()->integer('academic_year_id'))
            : AcademicYear::current();
        abort_if($year === null, 422, 'There is no current academic year.');

        return $year;
    }
}
