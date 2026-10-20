<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use App\Models\Section;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Support\SchoolClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Save a section's sheet for a day in one go (spec §14 bulk attendance).
 * Idempotent: saving the same section + date again updates the sheet and
 * its rows (unique indexes back this up). Every student on the day's roll
 * must be marked, and nobody else.
 */
class SaveAttendanceSheet
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * @param  list<array{student_id: int, status: string, remark?: ?string}>  $records
     * @return array{0: Attendance, 1: bool} the sheet, and whether it was created
     */
    public function execute(User $user, Section $section, string $date, array $records): array
    {
        $day = Carbon::parse($date)->toDateString();

        if ($day > SchoolClock::date()) {
            $this->fail('date', "Attendance can't be taken for a future date.");
        }
        $year = $this->attendance->yearFor($day) ?? $this->fail('date', "No academic year covers {$day}.");

        return DB::transaction(function () use ($user, $section, $day, $year, $records) {
            // Concurrent saves of the same section queue up instead of racing.
            Section::query()->whereKey($section->id)->lockForUpdate()->first();

            $sheet = Attendance::query()
                ->where('section_id', $section->id)
                ->whereDate('date', $day)
                ->first();

            abort_if(
                $sheet !== null && ! $user->can('attendance.edit'),
                403,
                "Attendance for {$day} is already taken; changing it needs edit permission.",
            );

            $roster = $this->attendance->roster($section, $year, $day)->keyBy('student_id');
            // Rows already on the sheet stay editable (e.g. a student archived since).
            $onSheet = $sheet?->details()->pluck('student_enrollment_id', 'student_id') ?? collect();

            $marked = collect($records)->keyBy('student_id');
            $unknown = $marked->keys()->reject(fn ($id) => $roster->has($id) || $onSheet->has($id));
            if ($unknown->isNotEmpty()) {
                $this->fail('records', 'Not on this section\'s roll for '.$day.': student #'.$unknown->join(', #').'.');
            }
            $missing = $roster->reject(fn ($e, $id) => $marked->has($id));
            if ($missing->isNotEmpty()) {
                $this->fail('records', 'Mark every student. Missing: '.$missing->map(fn ($e) => $e->student->name)->join(', ').'.');
            }

            $created = $sheet === null;
            $sheet ??= new Attendance([
                'academic_year_id' => $year->id,
                'class_id' => $section->class_id,
                'section_id' => $section->id,
                'date' => $day,
                'taken_by' => $user->id,
            ]);
            $sheet->updated_by = $user->id;
            $sheet->save();

            foreach ($marked as $studentId => $record) {
                $sheet->details()->updateOrCreate(
                    ['student_id' => $studentId],
                    [
                        'student_enrollment_id' => $roster[$studentId]->id ?? $onSheet[$studentId],
                        'date' => $day,
                        'status' => $record['status'],
                        'remark' => $record['remark'] ?? null,
                    ],
                );
            }

            return [$sheet, $created];
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
