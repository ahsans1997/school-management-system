<?php

namespace App\Actions\Routine;

use App\Models\AcademicYear;
use App\Models\Routine;
use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Services\Routine\RoutineService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or replace a section's routine for a year with a full week of
 * slots. Saving is blocked while any clash remains; the 422 carries a
 * `clashes` list (slot index, type, message) so the builder can mark rows.
 */
class SaveRoutine
{
    public function __construct(private readonly RoutineService $routines) {}

    /**
     * @param  list<array<string, mixed>>  $input
     */
    public function execute(Section $section, AcademicYear $year, array $input): Routine
    {
        $slots = array_map(fn (array $s) => [
            'day' => $s['day'],
            'period_no' => (int) $s['period_no'],
            'start_time' => $s['start_time'],
            'end_time' => $s['end_time'],
            'teaching_assignment_id' => isset($s['teaching_assignment_id']) ? (int) $s['teaching_assignment_id'] : null,
            'label' => isset($s['label']) && trim((string) $s['label']) !== '' ? trim($s['label']) : null,
            'room' => isset($s['room']) && trim((string) $s['room']) !== '' ? trim($s['room']) : null,
        ], $input);

        $errors = [];
        $ownAssignments = TeachingAssignment::query()->where('section_id', $section->id)->pluck('id')->all();
        foreach ($slots as $i => $slot) {
            if ($slot['start_time'] >= $slot['end_time']) {
                $errors["slots.{$i}.end_time"] = 'The period must end after it starts.';
            }
            if ($slot['teaching_assignment_id'] !== null && ! in_array($slot['teaching_assignment_id'], $ownAssignments, true)) {
                $errors["slots.{$i}.teaching_assignment_id"] = "That subject isn't assigned to this section.";
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($section, $year, $slots) {
            // One routine save per year at a time, so two sections can't
            // book the same teacher or room concurrently.
            Routine::query()->where('academic_year_id', $year->id)->lockForUpdate()->get();

            $clashes = $this->routines->clashes($section, $year, $slots);
            if ($clashes !== []) {
                throw new HttpResponseException(response()->json([
                    'message' => count($clashes) === 1 ? $clashes[0]['message'] : 'The routine has '.count($clashes).' clashes.',
                    'errors' => collect($clashes)->groupBy('index')
                        ->mapWithKeys(fn ($list, $i) => ["slots.{$i}" => $list->pluck('message')->all()])
                        ->all(),
                    'clashes' => $clashes,
                ], 422));
            }

            $routine = Routine::query()->firstOrCreate(
                ['section_id' => $section->id, 'academic_year_id' => $year->id],
                ['class_id' => $section->class_id],
            );
            $routine->slots()->delete();
            $routine->slots()->createMany($slots);
            $routine->touch();

            return $routine;
        });
    }
}
