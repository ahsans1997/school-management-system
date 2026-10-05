<?php

namespace Tests\Feature\Enrollment;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class EnrollmentTest extends EnrollmentTestCase
{
    public function test_a_student_is_enrolled_with_the_class_taken_from_the_section_and_the_next_roll(): void
    {
        $this->enroll($this->student('Nusrat'), $this->sixA)
            ->assertCreated()
            ->assertJsonPath('data.class.name', 'Class 6')
            ->assertJsonPath('data.section.name', 'A')
            ->assertJsonPath('data.academic_year.name', '2026')
            ->assertJsonPath('data.roll', 1)
            ->assertJsonPath('data.status', 'active');

        $this->enroll($this->student('Rafi'), $this->sixA)->assertJsonPath('data.roll', 2);
        $this->enroll($this->student('Mina'), $this->sixA, roll: 10)->assertJsonPath('data.roll', 10);
    }

    public function test_one_enrollment_per_student_per_year(): void
    {
        $nusrat = $this->student('Nusrat');
        $this->enroll($nusrat, $this->sixA)->assertCreated();

        $this->enroll($nusrat, $this->sixB)
            ->assertStatus(422)
            ->assertJsonPath('errors.student_id.0', 'Nusrat is already enrolled for 2026 (Class 6 A).');

        // Next year is fine.
        $this->enroll($nusrat, $this->sevenA, $this->y2027)->assertCreated();
    }

    public function test_rolls_are_unique_within_a_section_and_year(): void
    {
        $this->enroll($this->student('Nusrat'), $this->sixA, roll: 5)->assertCreated();

        $this->enroll($this->student('Rafi'), $this->sixA, roll: 5)
            ->assertStatus(422)
            ->assertJsonPath('errors.roll.0', 'Roll 5 is already taken in Class 6 A for 2026.');

        // Same roll in another section is fine.
        $this->enroll($this->student('Mina'), $this->sixB, roll: 5)->assertCreated();
    }

    public function test_capacity_is_enforced_and_archived_or_departed_students_free_their_seats(): void
    {
        $students = collect(['A1', 'A2', 'A3'])->map(fn ($n) => $this->student($n));
        $students->each(fn ($s) => $this->enroll($s, $this->sixA)->assertCreated());

        $this->enroll($this->student('Late'), $this->sixA)
            ->assertStatus(422)
            ->assertJsonPath('errors.section_id.0', 'Class 6 A is full for 2026 (3 of 3 seats taken).');

        $students[0]->delete(); // archived
        $this->enroll($this->student('Late'), $this->sixA)->assertCreated();

        // A transfer out frees a seat too.
        $enrollment = StudentEnrollment::query()->where('student_id', $students[1]->id)->sole();
        $this->asUser($this->admin)->putJson("/api/enrollments/{$enrollment->id}", [
            'section_id' => $this->sixA->id, 'status' => 'transferred', 'exit_reason' => 'Moved to Dhaka',
        ])->assertOk();
        $this->enroll($this->student('Later'), $this->sixA)->assertCreated();
    }

    public function test_archived_students_cannot_be_enrolled(): void
    {
        $gone = $this->student('Gone');
        $gone->delete();

        $this->enroll($gone, $this->sixA)
            ->assertStatus(422)
            ->assertJsonPath('errors.student_id.0', 'That student does not exist or is archived.');
    }

    public function test_the_database_refuses_a_class_that_disagrees_with_the_section(): void
    {
        $this->expectException(QueryException::class);

        DB::table('student_enrollments')->insert([
            'student_id' => $this->student('X')->id, 'academic_year_id' => $this->y2026->id,
            'class_id' => $this->seven->id, 'section_id' => $this->sixA->id, 'roll' => 1, 'status' => 'active',
        ]);
    }

    public function test_status_changes_record_an_end_date_and_reason(): void
    {
        $id = $this->enroll($this->student('Nusrat'), $this->sixA)->json('data.id');
        $this->travelTo('2026-06-15');

        $this->asUser($this->admin)->putJson("/api/enrollments/{$id}", [
            'section_id' => $this->sixA->id, 'status' => 'transferred', 'exit_reason' => 'Moved to Dhaka',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'transferred')
            ->assertJsonPath('data.ended_on', '2026-06-15')
            ->assertJsonPath('data.exit_reason', 'Moved to Dhaka')
            ->assertJsonPath('data.roll', 1);

        // Back to active clears them (and needs a free seat).
        $this->asUser($this->admin)->putJson("/api/enrollments/{$id}", ['section_id' => $this->sixA->id, 'status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.ended_on', null)
            ->assertJsonPath('data.exit_reason', null);
    }

    public function test_moving_to_another_section_takes_its_next_roll_and_needs_a_seat(): void
    {
        $this->enroll($this->student('B1'), $this->sixB);
        $id = $this->enroll($this->student('Nusrat'), $this->sixA)->json('data.id');

        $this->asUser($this->admin)->putJson("/api/enrollments/{$id}", ['section_id' => $this->sixB->id, 'status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.section.name', 'B')
            ->assertJsonPath('data.roll', 2);

        $this->enroll($this->student('B3'), $this->sixB);
        $other = $this->enroll($this->student('A-again'), $this->sixA)->json('data.id');
        $this->asUser($this->admin)->putJson("/api/enrollments/{$other}", ['section_id' => $this->sixB->id, 'status' => 'active'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('section_id');
    }

    public function test_list_filters_by_year_and_section_orders_by_roll_and_reports_seats(): void
    {
        $this->enroll($this->student('Second'), $this->sixA, roll: 2);
        $this->enroll($this->student('First'), $this->sixA, roll: 1);
        $this->enroll($this->student('Other section'), $this->sixB);
        $this->enroll($this->student('Next year'), $this->sevenA, $this->y2027);

        // Defaults to the current year.
        $this->asUser($this->admin)->getJson('/api/enrollments')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('academic_year_id', $this->y2026->id);

        $this->asUser($this->admin)->getJson("/api/enrollments?section_id={$this->sixA->id}")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.student.name', 'First')
            ->assertJsonPath('data.1.student.name', 'Second')
            ->assertJsonPath('seats', ['capacity' => 3, 'taken' => 2]);

        $this->asUser($this->admin)->getJson("/api/enrollments?academic_year_id={$this->y2027->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.name', 'Next year');
    }

    public function test_students_show_their_current_enrollment_and_history(): void
    {
        $nusrat = $this->student('Nusrat');
        $this->enroll($nusrat, $this->sixA);
        $this->enroll($nusrat, $this->sevenA, $this->y2027);

        $this->asUser($this->admin)->getJson('/api/students')
            ->assertJsonPath('data.0.current_enrollment.class.name', 'Class 6')
            ->assertJsonPath('data.0.current_enrollment.section.name', 'A')
            ->assertJsonPath('data.0.current_enrollment.roll', 1);

        $this->asUser($this->admin)->getJson("/api/students/{$nusrat->id}")
            ->assertJsonPath('data.current_enrollment.academic_year.name', '2026')
            ->assertJsonCount(2, 'data.enrollments')
            ->assertJsonPath('data.enrollments.0.academic_year.name', '2027'); // newest first

        $this->asUser($this->admin)->getJson("/api/students?section_id={$this->sixA->id}")->assertJsonCount(1, 'data');
        $this->asUser($this->admin)->getJson("/api/students?section_id={$this->sixB->id}")->assertJsonCount(0, 'data');
    }

    public function test_a_section_cannot_shrink_below_its_enrolled_students(): void
    {
        $this->enroll($this->student('A1'), $this->sixA);
        $this->enroll($this->student('A2'), $this->sixA);

        $this->asUser($this->admin)->putJson("/api/sections/{$this->sixA->id}", ['name' => 'A', 'capacity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('errors.capacity.0', "2 students are enrolled in this section for 2026; capacity can't be lower.");

        $this->asUser($this->admin)->putJson("/api/sections/{$this->sixA->id}", ['name' => 'A', 'capacity' => 2])->assertOk();
    }

    public function test_enrolled_years_sections_and_students_are_protected(): void
    {
        $id = $this->enroll($this->student('Nusrat'), $this->sixA)->json('data.id');

        $this->asUser($this->admin)->deleteJson("/api/sections/{$this->sixA->id}")->assertStatus(409);
        $this->asUser($this->admin)->deleteJson("/api/academic-years/{$this->y2027->id}")->assertNoContent(); // unused

        // 2026 holds an enrollment: once it's no longer current, deleting it is still refused.
        $other = AcademicYear::create(['name' => '2028', 'start_date' => '2028-01-01', 'end_date' => '2028-12-31', 'is_current' => false]);
        $this->asUser($this->admin)->postJson("/api/academic-years/{$other->id}/set-current")->assertOk();
        $this->asUser($this->admin)->deleteJson("/api/academic-years/{$this->y2026->id}")->assertStatus(409);

        // A wrong entry can be deleted outright.
        $this->asUser($this->admin)->deleteJson("/api/enrollments/{$id}")->assertNoContent();
    }

    public function test_permissions_gate_each_action(): void
    {
        $id = $this->enroll($this->student('Nusrat'), $this->sixA)->json('data.id');

        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson('/api/enrollments')->assertOk();
        $this->asUser($principal)->postJson('/api/enrollments', [
            'student_id' => $this->student('X')->id, 'academic_year_id' => $this->y2026->id, 'section_id' => $this->sixA->id,
        ])->assertForbidden();
        $this->asUser($principal)->putJson("/api/enrollments/{$id}", ['section_id' => $this->sixB->id, 'status' => 'active'])->assertForbidden();
        $this->asUser($principal)->postJson('/api/enrollments/promote', [])->assertForbidden();

        $this->asUser($this->userWithRole('Teacher'))->getJson('/api/enrollments')->assertForbidden();
    }
}
