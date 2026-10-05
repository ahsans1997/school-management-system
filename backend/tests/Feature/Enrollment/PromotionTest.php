<?php

namespace Tests\Feature\Enrollment;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;

class PromotionTest extends EnrollmentTestCase
{
    private function promote(array $studentIds, array $overrides = [])
    {
        return $this->asUser($this->admin)->postJson('/api/enrollments/promote', [
            'from_year_id' => $this->y2026->id,
            'from_section_id' => $this->sixA->id,
            'to_year_id' => $this->y2027->id,
            'to_section_id' => $this->sevenA->id,
            'student_ids' => $studentIds,
            ...$overrides,
        ]);
    }

    /** @return array<string, string> student name => status for a year */
    private function statuses(AcademicYear $year): array
    {
        return StudentEnrollment::query()->with('student')->where('academic_year_id', $year->id)->get()
            ->mapWithKeys(fn ($e) => [$e->student->name => "{$e->schoolClass->name} {$e->section->name} #{$e->roll} {$e->status->value}"])
            ->all();
    }

    public function test_chosen_students_move_up_a_class_into_the_next_year_in_roll_order(): void
    {
        $first = $this->student('First');
        $second = $this->student('Second');
        $held = $this->student('Held back');
        $this->enroll($second, $this->sixA, roll: 7);
        $this->enroll($first, $this->sixA, roll: 3);
        $this->enroll($held, $this->sixA, roll: 9);

        $this->promote([$second->id, $first->id])
            ->assertCreated()
            ->assertJsonPath('promoted', 2)
            ->assertJsonPath('data.0.student.name', 'First')
            ->assertJsonPath('data.0.roll', 1)
            ->assertJsonPath('data.1.roll', 2);

        $this->assertSame([
            'First' => 'Class 7 A #1 active',
            'Second' => 'Class 7 A #2 active',
        ], $this->statuses($this->y2027));
        $this->assertSame([
            'Second' => 'Class 6 A #7 promoted',
            'First' => 'Class 6 A #3 promoted',
            'Held back' => 'Class 6 A #9 active',
        ], $this->statuses($this->y2026));
    }

    public function test_the_target_must_be_one_class_up_in_a_later_year(): void
    {
        $s = $this->student('S');
        $this->enroll($s, $this->sixA);

        $this->promote([$s->id], ['to_section_id' => $this->sixB->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.to_section_id.0', 'Promote into a class one level above Class 6 (level 7).');

        $this->promote([$s->id], ['to_year_id' => $this->y2026->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.to_year_id.0', 'Promote into a later year than 2026.');
    }

    public function test_promotion_is_all_or_nothing(): void
    {
        $ok = $this->student('Fine');
        $clash = $this->student('Already next year');
        $this->enroll($ok, $this->sixA);
        $this->enroll($clash, $this->sixA);
        $this->enroll($clash, $this->sevenA, $this->y2027);

        $this->promote([$ok->id, $clash->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.student_ids.0', 'Already enrolled for 2027: Already next year.');

        // Nothing moved.
        $this->assertSame('Class 6 A #1 active', $this->statuses($this->y2026)['Fine']);
        $this->assertArrayNotHasKey('Fine', $this->statuses($this->y2027));
    }

    public function test_the_target_section_must_have_room_for_everyone(): void
    {
        $this->sixA->update(['capacity' => 5]);
        $ids = collect(range(1, 4))->map(function ($i) {
            $s = $this->student("S{$i}");
            $this->enroll($s, $this->sixA);

            return $s->id;
        })->all();

        $this->promote($ids)
            ->assertStatus(422)
            ->assertJsonPath('errors.section_id.0', 'Class 7 A has 3 free seat(s) for 2027; 4 students selected.');

        $this->assertSame(0, StudentEnrollment::query()->where('academic_year_id', $this->y2027->id)->count());
    }

    public function test_only_active_students_of_the_source_section_can_be_promoted(): void
    {
        $elsewhere = $this->student('In 6B');
        $this->enroll($elsewhere, $this->sixB);

        $this->promote([$elsewhere->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.student_ids.0', "Some selected students aren't active in Class 6 A for 2026.");
    }
}
