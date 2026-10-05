<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceDetail;
use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class AttendanceTest extends EnrollmentTestCase
{
    /** @var list<Student> students of 6A, rolls 1–3 */
    private array $sixAStudents;

    private User $teacherLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-06-20 09:00');

        $this->sixAStudents = array_map(function (string $name) {
            $s = $this->student($name);
            $this->enroll($s, $this->sixA)->assertCreated();

            return $s;
        }, ['Anika', 'Bilal', 'Chaity']);

        // Kamal teaches Mathematics in 6A only.
        $math = Subject::create(['name' => 'Mathematics']);
        $this->six->subjects()->attach($math->id);
        $kamal = Teacher::create(['name' => 'Kamal', 'employee_id' => 'T-1', 'joining_date' => '2020-01-01', 'phone' => '01700000000']);
        TeachingAssignment::create(['teacher_id' => $kamal->id, 'section_id' => $this->sixA->id, 'class_subject_id' => ClassSubject::query()->sole()->id]);
        $this->teacherLogin = User::factory()->create(['personable_type' => 'teacher', 'personable_id' => $kamal->id]);
        $this->teacherLogin->assignRole('Teacher');
    }

    /** @param  list<string>  $statuses  one per 6A student, in roll order */
    private function records(array $statuses): array
    {
        return array_map(fn (Student $s, string $status) => ['student_id' => $s->id, 'status' => $status], $this->sixAStudents, $statuses);
    }

    private function mark(User $user, string $date, array $statuses, $section = null)
    {
        return $this->asUser($user)->postJson('/api/attendance/bulk', [
            'section_id' => ($section ?? $this->sixA)->id,
            'date' => $date,
            'records' => $this->records($statuses),
        ]);
    }

    public function test_a_teacher_marks_their_whole_section_in_one_request(): void
    {
        $this->mark($this->teacherLogin, '2026-06-20', ['present', 'absent', 'late'])
            ->assertCreated()
            ->assertJsonPath('data.counts', ['present' => 1, 'absent' => 1, 'late' => 1, 'leave' => 0]);

        $this->asUser($this->teacherLogin)->getJson("/api/attendance/sheet?section_id={$this->sixA->id}&date=2026-06-20")
            ->assertOk()
            ->assertJsonPath('data.attendance.taken_by', $this->teacherLogin->name)
            ->assertJsonPath('data.students.0.name', 'Anika')
            ->assertJsonPath('data.students.0.roll', 1)
            ->assertJsonPath('data.students.1.status', 'absent');
    }

    public function test_a_teacher_cannot_touch_a_section_they_dont_teach(): void
    {
        $b = $this->student('In 6B');
        $this->enroll($b, $this->sixB);

        $this->asUser($this->teacherLogin)->getJson("/api/attendance/sheet?section_id={$this->sixB->id}&date=2026-06-20")
            ->assertForbidden();
        $this->asUser($this->teacherLogin)->postJson('/api/attendance/bulk', [
            'section_id' => $this->sixB->id, 'date' => '2026-06-20',
            'records' => [['student_id' => $b->id, 'status' => 'present']],
        ])->assertForbidden()->assertJsonPath('message', 'You can only work with the sections you teach.');
        $this->asUser($this->teacherLogin)->getJson("/api/attendance/monthly?section_id={$this->sixB->id}&month=2026-06")
            ->assertForbidden();

        // Being class teacher of 6B opens it up.
        $this->sixB->forceFill(['class_teacher_id' => Teacher::query()->sole()->id])->save();
        $this->asUser($this->teacherLogin)->getJson("/api/attendance/sheet?section_id={$this->sixB->id}&date=2026-06-20")
            ->assertOk();

        // The section list only offers their own sections.
        $this->assertSame(['A', 'B'], array_column($this->asUser($this->teacherLogin)->getJson('/api/attendance/sections')->json('data'), 'name'));
        $this->assertCount(3, $this->asUser($this->admin)->getJson('/api/attendance/sections')->json('data'));
    }

    public function test_saving_the_same_day_again_updates_instead_of_duplicating(): void
    {
        $this->mark($this->teacherLogin, '2026-06-20', ['present', 'present', 'present'])->assertCreated();
        $this->mark($this->teacherLogin, '2026-06-20', ['present', 'absent', 'present'])->assertOk();

        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame(3, AttendanceDetail::query()->count());
        $this->assertSame('absent', AttendanceDetail::query()->where('student_id', $this->sixAStudents[1]->id)->sole()->status->value);

        // And the database itself refuses a second row for the same student on a sheet.
        $this->expectException(QueryException::class);
        $detail = AttendanceDetail::query()->first();
        DB::table('attendance_details')->insert([
            'attendance_id' => $detail->attendance_id, 'student_enrollment_id' => $detail->student_enrollment_id,
            'student_id' => $detail->student_id, 'date' => '2026-06-20', 'status' => 'present',
        ]);
    }

    public function test_every_student_on_the_roll_must_be_marked_and_nobody_else(): void
    {
        $this->asUser($this->teacherLogin)->postJson('/api/attendance/bulk', [
            'section_id' => $this->sixA->id, 'date' => '2026-06-20',
            'records' => [['student_id' => $this->sixAStudents[0]->id, 'status' => 'present']],
        ])->assertStatus(422)->assertJsonPath('errors.records.0', 'Mark every student. Missing: Bilal, Chaity.');

        $outsider = $this->student('Outsider');
        $this->asUser($this->teacherLogin)->postJson('/api/attendance/bulk', [
            'section_id' => $this->sixA->id, 'date' => '2026-06-20',
            'records' => [...$this->records(['present', 'present', 'present']), ['student_id' => $outsider->id, 'status' => 'present']],
        ])->assertStatus(422)->assertJsonPath('errors.records.0', "Not on this section's roll for 2026-06-20: student #{$outsider->id}.");

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_dates_must_be_today_or_earlier_and_inside_an_academic_year(): void
    {
        $this->mark($this->teacherLogin, '2026-06-21', ['present', 'present', 'present'])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', "Attendance can't be taken for a future date.");

        $this->mark($this->admin, '2025-12-31', ['present', 'present', 'present'])
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', 'No academic year covers 2025-12-31.');
    }

    public function test_changing_a_taken_sheet_needs_edit_permission(): void
    {
        Role::create(['name' => 'Marker', 'guard_name' => 'api'])->givePermissionTo(['attendance.view', 'attendance.create']);
        $marker = $this->userWithRole('Marker');

        $this->mark($marker, '2026-06-20', ['present', 'present', 'present'])->assertCreated();
        $this->mark($marker, '2026-06-20', ['absent', 'present', 'present'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Attendance for 2026-06-20 is already taken; changing it needs edit permission.');

        // The edit endpoint itself is gated too, and works for those who hold it.
        $id = Attendance::query()->sole()->id;
        $this->asUser($marker)->putJson("/api/attendance/{$id}", ['records' => $this->records(['absent', 'present', 'present'])])->assertForbidden();
        $this->asUser($this->teacherLogin)->putJson("/api/attendance/{$id}", ['records' => $this->records(['absent', 'present', 'present'])])
            ->assertOk()
            ->assertJsonPath('data.counts.absent', 1);
    }

    public function test_the_roll_follows_the_date(): void
    {
        // Chaity transfers out on 15 June: still on the 10 June roll, not on the 16th.
        $enrollment = StudentEnrollment::query()->where('student_id', $this->sixAStudents[2]->id)->sole();
        $this->asUser($this->admin)->putJson("/api/enrollments/{$enrollment->id}", [
            'section_id' => $this->sixA->id, 'status' => 'transferred', 'ended_on' => '2026-06-15',
        ])->assertOk();

        $names = fn (string $date) => array_column(
            $this->asUser($this->admin)->getJson("/api/attendance/sheet?section_id={$this->sixA->id}&date={$date}")->json('data.students'),
            'name',
        );
        $this->assertSame(['Anika', 'Bilal', 'Chaity'], $names('2026-06-10'));
        $this->assertSame(['Anika', 'Bilal'], $names('2026-06-16'));
    }

    public function test_monthly_view_computes_percentages_server_side(): void
    {
        $this->travelTo('2026-06-02 09:00');
        $this->mark($this->teacherLogin, '2026-06-01', ['present', 'absent', 'leave']);
        $this->mark($this->teacherLogin, '2026-06-02', ['late', 'present', 'absent']);

        $data = $this->asUser($this->teacherLogin)->getJson("/api/attendance/monthly?section_id={$this->sixA->id}&month=2026-06")
            ->assertOk()
            ->json('data');

        $this->assertSame(['2026-06-01', '2026-06-02'], $data['days']);
        $byName = collect($data['students'])->keyBy('name');
        // Late counts as attended; leave doesn't.
        $this->assertSame(100.0, (float) $byName['Anika']['summary']['percentage']);
        $this->assertSame(50.0, (float) $byName['Bilal']['summary']['percentage']);
        $this->assertSame(0.0, (float) $byName['Chaity']['summary']['percentage']);
        $this->assertSame(['2026-06-01' => 'absent', '2026-06-02' => 'present'], $byName['Bilal']['statuses']);
    }

    public function test_student_report_lists_totals_months_and_exceptions(): void
    {
        $this->travelTo('2026-07-01 09:00');
        $this->mark($this->teacherLogin, '2026-06-30', ['absent', 'present', 'present']);
        $this->mark($this->teacherLogin, '2026-07-01', ['present', 'present', 'present']);
        $anika = $this->sixAStudents[0];

        $this->asUser($this->admin)->getJson("/api/students/{$anika->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.academic_year.name', '2026')
            ->assertJsonPath('data.summary.marked', 2)
            ->assertJsonPath('data.summary.percentage', 50)
            ->assertJsonPath('data.months.0.month', '2026-06')
            ->assertJsonPath('data.exceptions', [['date' => '2026-06-30', 'status' => 'absent', 'remark' => null]]);

        // A teacher outside the student's sections gets "missing", like the profile.
        $other = $this->student('Elsewhere');
        $this->enroll($other, $this->sixB);
        $this->asUser($this->teacherLogin)->getJson("/api/students/{$other->id}/attendance")->assertNotFound();
        $this->asUser($this->teacherLogin)->getJson("/api/students/{$anika->id}/attendance")->assertOk();
    }

    public function test_an_enrollment_with_attendance_cannot_be_deleted(): void
    {
        $this->mark($this->teacherLogin, '2026-06-20', ['present', 'present', 'present']);
        $enrollment = StudentEnrollment::query()->where('student_id', $this->sixAStudents[0]->id)->sole();

        $this->asUser($this->admin)->deleteJson("/api/enrollments/{$enrollment->id}")->assertStatus(409);
    }

    public function test_dashboards_show_attendance_to_each_audience(): void
    {
        $this->mark($this->teacherLogin, '2026-06-20', ['present', 'absent', 'late']);
        $widget = fn (User $u, string $key) => collect($this->asUser($u)->getJson('/api/dashboard')->json('data.widgets'))->firstWhere('key', $key)['data'];

        $today = $widget($this->admin, 'attendance_today');
        $this->assertSame([1, 1], [$today['sections_total'], $today['sections_marked']]);
        $this->assertSame(66.7, (float) $today['percentage']);

        $this->assertSame([['section_id' => $this->sixA->id, 'class' => 'Class 6', 'section' => 'A', 'marked' => true]], $widget($this->teacherLogin, 'attendance_tasks')['sections']);

        $guardian = Guardian::create(['name' => 'Parent', 'phone' => '01800000000']);
        $guardian->students()->attach($this->sixAStudents[1]->id, ['relationship' => 'mother', 'is_primary' => true]);
        $parent = User::factory()->create(['personable_type' => 'guardian', 'personable_id' => $guardian->id]);
        $parent->assignRole('Parent');
        $child = $widget($parent, 'child_attendance')['children'][0];
        $this->assertSame(['Bilal', 0.0], [$child['name'], (float) $child['percentage']]);

        $studentLogin = User::factory()->create(['personable_type' => 'student', 'personable_id' => $this->sixAStudents[2]->id]);
        $studentLogin->assignRole('Student');
        $this->assertSame(100.0, (float) $widget($studentLogin, 'my_attendance')['summary']['percentage']);
    }

    public function test_permissions_gate_each_endpoint(): void
    {
        $accountant = $this->userWithRole('Accountant'); // no attendance.*
        $this->asUser($accountant)->getJson('/api/attendance/sections')->assertForbidden();

        $receptionist = $this->userWithRole('Receptionist'); // attendance.view only
        $this->asUser($receptionist)->getJson("/api/attendance/sheet?section_id={$this->sixA->id}&date=2026-06-20")->assertOk();
        $this->mark($receptionist, '2026-06-20', ['present', 'present', 'present'])->assertForbidden();
    }
}
