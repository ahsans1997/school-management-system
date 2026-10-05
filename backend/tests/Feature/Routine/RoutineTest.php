<?php

namespace Tests\Feature\Routine;

use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class RoutineTest extends EnrollmentTestCase
{
    private Teacher $kamal;

    private Teacher $farhana;

    /** @var array<string, TeachingAssignment> e.g. "6A-Mathematics" */
    private array $teaching = [];

    protected function setUp(): void
    {
        parent::setUp();
        $math = Subject::create(['name' => 'Mathematics']);
        $english = Subject::create(['name' => 'English']);
        foreach ([$this->six, $this->seven] as $class) {
            $class->subjects()->attach([$math->id, $english->id]);
        }

        $this->kamal = Teacher::create(['name' => 'Kamal', 'employee_id' => 'T-1', 'joining_date' => '2020-01-01', 'phone' => '01700000001']);
        $this->farhana = Teacher::create(['name' => 'Farhana', 'employee_id' => 'T-2', 'joining_date' => '2020-01-01', 'phone' => '01700000002']);

        $this->assign($this->kamal, $this->sixA, $math, '6A');
        $this->assign($this->kamal, $this->sevenA, $math, '7A');
        $this->assign($this->farhana, $this->sixA, $english, '6A');
        $this->assign($this->farhana, $this->sixB, $english, '6B');
    }

    private function assign(Teacher $teacher, Section $section, Subject $subject, string $label): void
    {
        $cs = ClassSubject::query()->where('class_id', $section->class_id)->where('subject_id', $subject->id)->sole();
        $this->teaching["{$label}-{$subject->name}"] = TeachingAssignment::create([
            'teacher_id' => $teacher->id, 'section_id' => $section->id, 'class_subject_id' => $cs->id,
        ]);
    }

    private function slot(string $teaching, string $day, int $period, string $start, string $end, ?string $room = null): array
    {
        return [
            'day' => $day, 'period_no' => $period, 'start_time' => $start, 'end_time' => $end,
            'teaching_assignment_id' => $this->teaching[$teaching]->id, 'room' => $room,
        ];
    }

    private function save(Section $section, array $slots, ?User $as = null)
    {
        return $this->asUser($as ?? $this->admin)->postJson('/api/routines', [
            'section_id' => $section->id, 'academic_year_id' => $this->y2026->id, 'slots' => $slots,
        ]);
    }

    private function loginFor(Teacher $teacher): User
    {
        $user = User::factory()->create(['personable_type' => 'teacher', 'personable_id' => $teacher->id]);
        $user->assignRole('Teacher');

        return $user;
    }

    public function test_a_section_routine_is_built_from_its_teaching_assignments(): void
    {
        $this->save($this->sixA, [
            $this->slot('6A-English', 'sun', 2, '08:45', '09:30'),
            $this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45', '101'),
            ['day' => 'sun', 'period_no' => 3, 'start_time' => '09:30', 'end_time' => '09:50', 'label' => 'Tiffin'],
        ])->assertCreated();

        $data = $this->asUser($this->admin)->getJson("/api/sections/{$this->sixA->id}/routine")->assertOk()->json('data');

        $this->assertSame(['Mathematics', 'English', null], array_map(fn ($s) => $s['subject']['name'] ?? null, $data['slots']));
        $this->assertSame(['Kamal', 'Farhana', null], array_map(fn ($s) => $s['teacher']['name'] ?? null, $data['slots']));
        $this->assertSame(['08:00', '101'], [$data['slots'][0]['start_time'], $data['slots'][0]['room']]);
        $this->assertSame('Tiffin', $data['slots'][2]['label']);
        $this->assertSame(['English', 'Mathematics'], array_column($data['options'], 'subject'));
    }

    public function test_a_teacher_cannot_be_in_two_places_at_once(): void
    {
        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45')])->assertCreated();

        $this->save($this->sevenA, [$this->slot('7A-Mathematics', 'sun', 1, '08:30', '09:15')])
            ->assertStatus(422)
            ->assertJsonPath('clashes.0.type', 'teacher')
            ->assertJsonPath('clashes.0.index', 0)
            ->assertJsonPath('message', 'Kamal already teaches Class 6 A on Sunday 08:00–08:45 (Mathematics).');

        // Back-to-back is fine; so is the same time on another day.
        $this->save($this->sevenA, [
            $this->slot('7A-Mathematics', 'sun', 2, '08:45', '09:30'),
            $this->slot('7A-Mathematics', 'mon', 1, '08:00', '08:45'),
        ])->assertCreated();
    }

    public function test_a_room_cannot_host_two_sections_at_once(): void
    {
        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45', 'Lab 1')])->assertCreated();

        $this->save($this->sixB, [$this->slot('6B-English', 'sun', 1, '08:15', '09:00', 'lab 1')])
            ->assertStatus(422)
            ->assertJsonPath('clashes.0.type', 'room')
            ->assertJsonPath('errors', ['slots.0' => ['Room Lab 1 is used by Class 6 A on Sunday 08:00–08:45.']]);
    }

    public function test_a_sections_own_periods_cannot_overlap_or_repeat(): void
    {
        $this->save($this->sixA, [
            $this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45'),
            $this->slot('6A-English', 'sun', 2, '08:30', '09:15'),
            $this->slot('6A-English', 'sun', 1, '10:00', '10:45'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('clashes.0.message', 'Overlaps period 1 on Sunday (08:00–08:45).')
            ->assertJsonPath('clashes.1.message', 'Period 1 appears twice on Sunday.');
    }

    public function test_slots_are_validated(): void
    {
        $this->save($this->sixA, [
            $this->slot('6A-Mathematics', 'sun', 1, '09:00', '08:00'),
            $this->slot('6B-English', 'sun', 2, '10:00', '10:45'),          // another section's assignment
            ['day' => 'sun', 'period_no' => 3, 'start_time' => '11:00', 'end_time' => '11:30'], // no subject, no label
        ])->assertStatus(422)->assertJsonValidationErrors(['slots.2.teaching_assignment_id']);

        $this->save($this->sixA, [
            $this->slot('6A-Mathematics', 'sun', 1, '09:00', '08:00'),
            $this->slot('6B-English', 'sun', 2, '10:00', '10:45'),
        ])->assertStatus(422)->assertJsonValidationErrors([
            'slots.0.end_time' => 'The period must end after it starts.',
            'slots.1.teaching_assignment_id' => "That subject isn't assigned to this section.",
        ]);
    }

    public function test_replacing_a_routine_needs_edit_permission(): void
    {
        Role::create(['name' => 'Planner', 'guard_name' => 'api'])->givePermissionTo(['routine.view', 'routine.create']);
        $planner = $this->userWithRole('Planner');

        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45')], $planner)->assertCreated();
        $this->save($this->sixA, [], $planner)
            ->assertForbidden()
            ->assertJsonPath('message', 'This section already has a routine; changing it needs edit permission.');

        $id = $this->asUser($this->admin)->getJson("/api/sections/{$this->sixA->id}/routine")->json('data.routine.id');
        $this->asUser($this->admin)->putJson("/api/routines/{$id}", ['slots' => [$this->slot('6A-English', 'mon', 1, '08:00', '08:45')]])
            ->assertOk()
            ->assertJsonPath('data.slots.0.subject.name', 'English')
            ->assertJsonCount(1, 'data.slots');
    }

    public function test_teacher_and_room_views_come_from_the_same_slots(): void
    {
        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45', '101')]);
        $this->save($this->sevenA, [$this->slot('7A-Mathematics', 'mon', 1, '08:00', '08:45', '101')]);

        $kamalWeek = $this->asUser($this->admin)->getJson("/api/routines/teacher/{$this->kamal->id}")->json('data.slots');
        $this->assertSame(['Class 6 A', 'Class 7 A'], array_map(fn ($s) => "{$s['class']['name']} {$s['section']['name']}", $kamalWeek));

        $room = $this->asUser($this->admin)->getJson('/api/routines/room?room=101')->json('data.slots');
        $this->assertSame(['sun', 'mon'], array_column($room, 'day'));
    }

    public function test_teachers_see_only_their_own_sections_and_routine(): void
    {
        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45')]);
        $farhana = $this->loginFor($this->farhana);

        $this->asUser($farhana)->getJson("/api/sections/{$this->sixA->id}/routine")->assertOk();
        $this->asUser($farhana)->getJson("/api/sections/{$this->sevenA->id}/routine")->assertForbidden();
        $this->asUser($farhana)->getJson("/api/routines/teacher/{$this->kamal->id}")->assertForbidden();
        $this->asUser($farhana)->getJson("/api/routines/teacher/{$this->farhana->id}")->assertOk();
        $this->assertSame(['A'], array_column(array_column($this->asUser($farhana)->getJson('/api/routines')->json('data'), 'section'), 'name'));
        $this->save($this->sixB, [], $farhana)->assertForbidden(); // no routine.create
    }

    public function test_me_serves_each_portal_its_own_routine(): void
    {
        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45')]);
        $this->save($this->sixB, [$this->slot('6B-English', 'sun', 1, '08:00', '08:45')]);

        // Teacher: their own teaching week.
        $kamal = $this->loginFor($this->kamal);
        $this->asUser($kamal)->getJson('/api/routines/me')
            ->assertOk()
            ->assertJsonPath('data.type', 'teacher')
            ->assertJsonPath('data.slots.0.section.name', 'A');

        // Student: exactly their own section.
        $nusrat = $this->student('Nusrat');
        $this->enroll($nusrat, $this->sixB);
        $studentLogin = User::factory()->create(['personable_type' => 'student', 'personable_id' => $nusrat->id]);
        $studentLogin->assignRole('Student');
        $this->asUser($studentLogin)->getJson('/api/routines/me')
            ->assertJsonPath('data.type', 'student')
            ->assertJsonCount(1, 'data.weeks')
            ->assertJsonPath('data.weeks.0.section.name', 'B')
            ->assertJsonPath('data.weeks.0.slots.0.subject.name', 'English');

        // Parent: one week per child.
        $guardian = Guardian::create(['name' => 'Parent', 'phone' => '01800000000']);
        $guardian->students()->attach($nusrat->id, ['relationship' => 'mother', 'is_primary' => true]);
        $parent = User::factory()->create(['personable_type' => 'guardian', 'personable_id' => $guardian->id]);
        $parent->assignRole('Parent');
        $this->asUser($parent)->getJson('/api/routines/me')
            ->assertJsonPath('data.type', 'parent')
            ->assertJsonPath('data.weeks.0.student.name', 'Nusrat');

        // Neither students nor parents can browse other routines.
        $this->asUser($studentLogin)->getJson("/api/sections/{$this->sixA->id}/routine")->assertForbidden();
    }

    public function test_a_scheduled_assignment_cannot_be_removed(): void
    {
        $this->save($this->sixA, [$this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45')]);

        $this->asUser($this->admin)
            ->deleteJson("/api/teachers/{$this->kamal->id}/subjects/{$this->teaching['6A-Mathematics']->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This subject is in the class routine. Remove those periods first.');
    }

    public function test_dashboards_show_todays_periods(): void
    {
        $this->travelTo('2026-06-21 07:00'); // a Sunday
        $this->save($this->sixA, [
            $this->slot('6A-Mathematics', 'sun', 1, '08:00', '08:45'),
            $this->slot('6A-English', 'mon', 1, '08:00', '08:45'),
        ]);
        $widget = fn (User $u, string $key) => collect($this->asUser($u)->getJson('/api/dashboard')->json('data.widgets'))->firstWhere('key', $key)['data'];

        $today = $widget($this->loginFor($this->kamal), 'todays_classes');
        $this->assertSame('sun', $today['day']);
        $this->assertSame(['Mathematics'], array_column(array_column($today['periods'], 'subject'), 'name'));

        $s = $this->student('Pupil');
        $this->enroll($s, $this->sixA);
        $pupil = User::factory()->create(['personable_type' => 'student', 'personable_id' => $s->id]);
        $pupil->assignRole('Student');
        $this->assertCount(1, $widget($pupil, 'my_routine')['periods']);
    }
}
