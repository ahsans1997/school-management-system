<?php

namespace App\Services\Routine;

use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Routine;
use App\Models\RoutineSlot;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every routine view (section, teacher, room, "mine") is a query over the
 * same slots, and clash detection compares time ranges, not just start
 * times: two periods clash when one starts before the other ends.
 */
class RoutineService
{
    private const SLOT_WITH = [
        'routine.section.schoolClass',
        'teachingAssignment.teacher',
        'teachingAssignment.classSubject.subject',
    ];

    /** @return array<string, mixed> */
    public function slotView(RoutineSlot $slot): array
    {
        $assignment = $slot->teachingAssignment;

        return [
            'id' => $slot->id,
            'day' => $slot->day->value,
            'period_no' => $slot->period_no,
            'start_time' => $slot->startsAt(),
            'end_time' => $slot->endsAt(),
            'label' => $slot->label,
            'room' => $slot->room,
            'teaching_assignment_id' => $slot->teaching_assignment_id,
            'subject' => $assignment ? ['id' => $assignment->classSubject->subject->id, 'name' => $assignment->classSubject->subject->name] : null,
            'teacher' => $assignment ? ['id' => $assignment->teacher->id, 'name' => $assignment->teacher->name] : null,
            'class' => ['id' => $slot->routine->section->schoolClass->id, 'name' => $slot->routine->section->schoolClass->name],
            'section' => ['id' => $slot->routine->section->id, 'name' => $slot->routine->section->name],
        ];
    }

    /**
     * @param  iterable<RoutineSlot>  $slots
     * @return list<array<string, mixed>> in week order, then by start time
     */
    public function present(iterable $slots): array
    {
        return collect($slots)
            ->sortBy(fn (RoutineSlot $s) => [$s->day->order(), $s->startsAt(), $s->period_no])
            ->map(fn (RoutineSlot $s) => $this->slotView($s))
            ->values()
            ->all();
    }

    /** A section's routine for a year, plus the subject/teacher choices for the builder. */
    public function forSection(Section $section, AcademicYear $year): array
    {
        $routine = Routine::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->first();

        $options = TeachingAssignment::query()
            ->with(['teacher', 'classSubject.subject'])
            ->where('section_id', $section->id)
            ->get()
            ->sortBy(fn (TeachingAssignment $a) => $a->classSubject->subject->name)
            ->map(fn (TeachingAssignment $a) => [
                'teaching_assignment_id' => $a->id,
                'subject' => $a->classSubject->subject->name,
                'teacher' => $a->teacher->name,
            ])
            ->values();

        return [
            'routine' => $routine ? ['id' => $routine->id, 'updated_at' => $routine->updated_at?->toIso8601String()] : null,
            'slots' => $routine ? $this->present($routine->slots()->with(self::SLOT_WITH)->get()) : [],
            'options' => $options,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function forTeacher(Teacher $teacher, AcademicYear $year): array
    {
        return $this->present($this->slotsIn($year)
            ->whereHas('teachingAssignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->get());
    }

    /** @return list<array<string, mixed>> */
    public function forRoom(string $room, AcademicYear $year): array
    {
        return $this->present($this->slotsIn($year)->where('room', trim($room))->get());
    }

    /**
     * Clashes for a proposed set of slots of $section's routine in $year.
     * Checked: periods of the section overlapping each other, a teacher in
     * two places at once (across every routine of the year), and a room
     * used by two sections at once.
     *
     * @param  list<array{day: string, period_no: int, start_time: string, end_time: string, teaching_assignment_id: ?int, room: ?string}>  $slots
     * @return list<array{index: int, type: string, message: string}>
     */
    public function clashes(Section $section, AcademicYear $year, array $slots): array
    {
        $clashes = [];
        $overlaps = fn (array $a, string $start, string $end) => $a['start_time'] < $end && $start < $a['end_time'];
        $day = fn (string $value) => Weekday::from($value)->label();

        // Within the section itself.
        foreach ($slots as $i => $a) {
            foreach ($slots as $j => $b) {
                if ($j <= $i || $a['day'] !== $b['day']) {
                    continue;
                }
                if ($a['period_no'] === $b['period_no']) {
                    $clashes[] = ['index' => $j, 'type' => 'section', 'message' => "Period {$b['period_no']} appears twice on {$day($b['day'])}."];
                } elseif ($overlaps($a, $b['start_time'], $b['end_time'])) {
                    $clashes[] = ['index' => $j, 'type' => 'section', 'message' => "Overlaps period {$a['period_no']} on {$day($b['day'])} ({$a['start_time']}–{$a['end_time']})."];
                }
            }
        }

        // Other sections' slots this year that could clash on teacher or room.
        $assignments = TeachingAssignment::query()->with('teacher')
            ->whereKey(array_filter(array_column($slots, 'teaching_assignment_id')))->get()->keyBy('id');
        $teacherIds = $assignments->pluck('teacher_id')->unique()->all();
        $anyRoom = collect($slots)->pluck('room')->filter()->isNotEmpty();

        /** @var Collection<int, RoutineSlot> $others */
        $others = $this->slotsIn($year)
            ->whereHas('routine', fn ($q) => $q->where('section_id', '!=', $section->id))
            ->where(function ($q) use ($teacherIds, $anyRoom) {
                $q->whereHas('teachingAssignment', fn ($a) => $a->whereIn('teacher_id', $teacherIds ?: [0]));
                // Rooms are compared case-insensitively below, whatever the DB collation.
                if ($anyRoom) {
                    $q->orWhereNotNull('room');
                }
            })
            ->get();

        foreach ($slots as $i => $slot) {
            $teacher = $assignments->get($slot['teaching_assignment_id'] ?? 0)?->teacher;
            foreach ($others as $other) {
                if ($other->day->value !== $slot['day'] || ! $overlaps(['start_time' => $other->startsAt(), 'end_time' => $other->endsAt()], $slot['start_time'], $slot['end_time'])) {
                    continue;
                }
                $where = "{$other->routine->section->schoolClass->name} {$other->routine->section->name} on {$day($slot['day'])} {$other->startsAt()}–{$other->endsAt()}";

                if ($teacher !== null && $other->teachingAssignment?->teacher_id === $teacher->id) {
                    $subject = $other->teachingAssignment->classSubject->subject->name;
                    $clashes[] = ['index' => $i, 'type' => 'teacher', 'message' => "{$teacher->name} already teaches {$where} ({$subject})."];
                }
                if (! empty($slot['room']) && $other->room !== null && mb_strtolower(trim($other->room)) === mb_strtolower(trim($slot['room']))) {
                    $clashes[] = ['index' => $i, 'type' => 'room', 'message' => "Room {$other->room} is used by {$where}."];
                }
            }
        }

        return $clashes;
    }

    /** @return Builder<RoutineSlot> */
    private function slotsIn(AcademicYear $year): Builder
    {
        return RoutineSlot::query()
            ->with(self::SLOT_WITH)
            ->whereHas('routine', fn ($q) => $q->where('academic_year_id', $year->id));
    }
}
