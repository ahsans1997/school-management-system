<?php

namespace Tests\Feature\Student;

use App\Models\ClassSubject;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class IdCardAndCertificateTest extends EnrollmentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-06 10:00');
    }

    private function enrolled(string $name, $section, ?int $roll = null): Student
    {
        $student = $this->student($name);
        $this->enroll($student, $section, roll: $roll)->assertCreated();

        return $student;
    }

    private function teacherOf($section): User
    {
        $subject = Subject::create(['name' => 'Bangla '.$section->id]);
        $section->schoolClass->subjects()->attach($subject->id);
        $teacher = Teacher::create(['name' => 'Kamal', 'employee_id' => 'T-'.$section->id, 'joining_date' => '2020-01-01', 'phone' => '01700000001']);
        TeachingAssignment::create([
            'teacher_id' => $teacher->id, 'section_id' => $section->id,
            'class_subject_id' => ClassSubject::query()->where('subject_id', $subject->id)->sole()->id,
        ]);
        $user = User::factory()->create(['personable_type' => 'teacher', 'personable_id' => $teacher->id]);
        $user->assignRole('Teacher');

        return $user;
    }

    private function transfer(Student $student, string $reason = 'Moved to Chattogram')
    {
        $enrollment = StudentEnrollment::query()->where('student_id', $student->id)->sole();
        $this->asUser($this->admin)->putJson("/api/enrollments/{$enrollment->id}", [
            'section_id' => $enrollment->section_id, 'roll' => $enrollment->roll,
            'status' => 'transferred', 'ended_on' => '2026-10-01', 'exit_reason' => $reason,
        ])->assertOk();

        return $enrollment;
    }

    public function test_an_enrolled_student_gets_an_id_card_for_the_year(): void
    {
        $nusrat = $this->enrolled('Nusrat', $this->sixA);
        $nusrat->update(['blood_group' => 'B+', 'phone' => '01811111111']);
        $guardian = Guardian::create(['name' => 'Rahima', 'phone' => '01922222222']);
        $nusrat->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        $data = $this->asUser($this->admin)->getJson("/api/students/{$nusrat->id}/id-card")->assertOk()->json('data');

        $this->assertSame(['2026', '2026-12-31'], [$data['academic_year']['name'], $data['academic_year']['valid_until']]);
        $card = $data['cards'][0];
        $this->assertSame(['Nusrat', 'Class 6', 'A', 1, 'B+'], [$card['name'], $card['class'], $card['section'], $card['roll'], $card['blood_group']]);
        $this->assertSame($nusrat->student_id, $card['id_no']);
        $this->assertSame('01922222222', $card['contact_phone']); // the primary guardian before the student's own
        $this->assertArrayHasKey('name', $data['school']);

        // No card without an active enrollment that year; none for archived students.
        $rafi = $this->student('Rafi');
        $this->asUser($this->admin)->getJson("/api/students/{$rafi->id}/id-card")->assertUnprocessable();
        $this->asUser($this->admin)->getJson("/api/students/{$nusrat->id}/id-card?academic_year_id={$this->y2027->id}")->assertUnprocessable();
        $nusrat->delete();
        $this->asUser($this->admin)->getJson("/api/students/{$nusrat->id}/id-card")->assertNotFound();
    }

    public function test_a_section_prints_its_active_students_cards_in_roll_order(): void
    {
        $this->enrolled('Rafi', $this->sixA, 2);
        $this->enrolled('Nusrat', $this->sixA, 1);
        $left = $this->enrolled('Tania', $this->sixA, 3);
        $this->transfer($left);
        $this->enrolled('Anika', $this->sixB);

        $data = $this->asUser($this->admin)->getJson("/api/sections/{$this->sixA->id}/id-cards")->assertOk()->json('data');

        $this->assertSame(['Nusrat', 'Rafi'], array_column($data['cards'], 'name'));
        $this->assertSame(['Class 6', 'A'], [$data['section']['class'], $data['section']['name']]);
    }

    public function test_teachers_print_cards_only_for_their_own_sections(): void
    {
        $nusrat = $this->enrolled('Nusrat', $this->sixA);
        $anika = $this->enrolled('Anika', $this->sixB);
        $teacher = $this->teacherOf($this->sixA);

        $this->asUser($teacher)->getJson("/api/sections/{$this->sixA->id}/id-cards")->assertOk()->assertJsonCount(1, 'data.cards');
        $this->asUser($teacher)->getJson("/api/sections/{$this->sixB->id}/id-cards")->assertForbidden();
        $this->asUser($teacher)->getJson("/api/students/{$nusrat->id}/id-card")->assertOk();
        $this->asUser($teacher)->getJson("/api/students/{$anika->id}/id-card")->assertNotFound();
        $this->asUser($this->userWithRole('Parent'))->getJson("/api/students/{$nusrat->id}/id-card")->assertForbidden();
    }

    public function test_a_transfer_certificate_is_issued_once_with_a_fixed_serial(): void
    {
        $nusrat = $this->enrolled('Nusrat', $this->sixA);
        $nusrat->update(['father_name' => 'Abdul Karim']);
        $enrollment = StudentEnrollment::query()->where('student_id', $nusrat->id)->sole();

        // Only a transferred enrollment qualifies.
        $this->asUser($this->admin)->postJson("/api/enrollments/{$enrollment->id}/transfer-certificate")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A transfer certificate can only be issued once the enrollment is marked as transferred.');
        $this->transfer($nusrat);
        $this->asUser($this->admin)->getJson("/api/enrollments/{$enrollment->id}/transfer-certificate")->assertNotFound();

        $first = $this->asUser($this->admin)->postJson("/api/enrollments/{$enrollment->id}/transfer-certificate")
            ->assertCreated()->json('data');
        $this->assertSame(['TC-2026-0001', '2026-10-06', 'Good'], [$first['serial_no'], $first['issued_on'], $first['conduct']]);
        $this->assertSame(['Nusrat', 'Abdul Karim'], [$first['student']['name'], $first['student']['father_name']]);
        $this->assertSame(['2026', 'Class 6', 'A', '2026-10-01', 'Moved to Chattogram'], [
            $first['enrollment']['academic_year'], $first['enrollment']['class'], $first['enrollment']['section'],
            $first['enrollment']['left_on'], $first['enrollment']['reason'],
        ]);

        // Issuing again returns the same certificate; only the conduct can change.
        $this->travelTo('2026-10-09 10:00');
        $again = $this->asUser($this->admin)->postJson("/api/enrollments/{$enrollment->id}/transfer-certificate", ['conduct' => 'Excellent'])
            ->assertOk()->json('data');
        $this->assertSame(['TC-2026-0001', '2026-10-06', 'Excellent'], [$again['serial_no'], $again['issued_on'], $again['conduct']]);

        // Still printable after the student is archived; the next one gets the next serial.
        $nusrat->delete();
        $this->asUser($this->admin)->getJson("/api/enrollments/{$enrollment->id}/transfer-certificate")
            ->assertOk()->assertJsonPath('data.serial_no', 'TC-2026-0001');
        $rafi = $this->enrolled('Rafi', $this->sixB);
        $rafiEnrollment = $this->transfer($rafi);
        $this->asUser($this->admin)->postJson("/api/enrollments/{$rafiEnrollment->id}/transfer-certificate")
            ->assertCreated()->assertJsonPath('data.serial_no', 'TC-2026-0002');
    }

    public function test_viewing_and_issuing_certificates_need_their_permissions(): void
    {
        $nusrat = $this->enrolled('Nusrat', $this->sixA);
        $enrollment = $this->transfer($nusrat);
        $this->asUser($this->admin)->postJson("/api/enrollments/{$enrollment->id}/transfer-certificate")->assertCreated();

        $principal = $this->userWithRole('Principal'); // enrollment.view only
        $this->asUser($principal)->getJson("/api/enrollments/{$enrollment->id}/transfer-certificate")->assertOk();
        $this->asUser($principal)->postJson("/api/enrollments/{$enrollment->id}/transfer-certificate")->assertForbidden();
        $this->asUser($this->userWithRole('Teacher'))->getJson("/api/enrollments/{$enrollment->id}/transfer-certificate")->assertForbidden();
    }
}
