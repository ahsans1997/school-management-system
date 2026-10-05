<?php

namespace Tests\Feature\Exam;

use App\Models\Exam;
use App\Models\GradeScale;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class GradeScaleTest extends EnrollmentTestCase
{
    /** The Bangladesh GPA 5.00 bands, highest first. */
    public const STANDARD = [
        ['name' => 'A+', 'grade_point' => 5, 'min_percent' => 80],
        ['name' => 'A', 'grade_point' => 4, 'min_percent' => 70],
        ['name' => 'A-', 'grade_point' => 3.5, 'min_percent' => 60],
        ['name' => 'B', 'grade_point' => 3, 'min_percent' => 50],
        ['name' => 'C', 'grade_point' => 2, 'min_percent' => 40],
        ['name' => 'D', 'grade_point' => 1, 'min_percent' => 33],
        ['name' => 'F', 'grade_point' => 0, 'min_percent' => 0],
    ];

    private function create(array $grades = self::STANDARD, string $name = 'GPA 5')
    {
        return $this->asUser($this->admin)->postJson('/api/grade-scales', ['name' => $name, 'grades' => $grades]);
    }

    public function test_a_scale_is_stored_as_cut_offs_and_shown_as_ranges(): void
    {
        // Sent in any order; returned highest band first.
        $data = $this->create(array_reverse(self::STANDARD))->assertCreated()->json('data');

        $this->assertSame(['A+', 'A', 'A-', 'B', 'C', 'D', 'F'], array_column($data['grades'], 'name'));
        $this->assertEquals([80, null], [$data['grades'][0]['min_percent'], $data['grades'][0]['below_percent']]);
        $this->assertEquals([70, 80], [$data['grades'][1]['min_percent'], $data['grades'][1]['below_percent']]);
        $this->assertEquals(3.5, $data['grades'][2]['grade_point']);

        // A 79.5 % mark can't fall into a gap between "70–79" and "80–100".
        $scale = GradeScale::with('grades')->find($data['id']);
        $this->assertSame('A', $scale->gradeFor(79.5)->name);
        $this->assertSame('A+', $scale->gradeFor(80)->name);
        $this->assertSame('D', $scale->gradeFor(33)->name);
        $this->assertSame('F', $scale->gradeFor(0)->name);
    }

    public function test_bands_must_cover_every_mark_without_overlapping(): void
    {
        $withoutF = array_slice(self::STANDARD, 0, 6);
        $this->create($withoutF)->assertUnprocessable()
            ->assertJsonPath('errors.grades.0', 'One band must start at 0 % so every mark gets a grade.');

        $twoAt70 = self::STANDARD;
        $twoAt70[0]['min_percent'] = 70;
        $this->create($twoAt70)->assertUnprocessable()->assertJsonValidationErrors(['grades.1.min_percent']);

        $twoAs = self::STANDARD;
        $twoAs[1]['name'] = 'a+';
        $this->create($twoAs)->assertUnprocessable()->assertJsonValidationErrors(['grades.1.name']);

        $inverted = self::STANDARD;
        $inverted[3]['grade_point'] = 4.5; // B above A-
        $this->create($inverted)->assertUnprocessable()->assertJsonValidationErrors(['grades.3.grade_point']);

        $this->create([['name' => 'F', 'grade_point' => 0, 'min_percent' => 101]])->assertUnprocessable()
            ->assertJsonValidationErrors(['grades.0.min_percent']);
        $this->assertSame(0, GradeScale::count());
    }

    public function test_editing_keeps_the_bands_it_is_sent_and_drops_the_rest(): void
    {
        $scale = $this->create()->json('data');
        $ids = array_column($scale['grades'], 'id', 'name');

        // Swap two names (would trip the unique index without placeholders),
        // move a cut-off, drop A-, and add a new band.
        $grades = [
            ['id' => $ids['A+'], 'name' => 'A', 'grade_point' => 5, 'min_percent' => 85],
            ['id' => $ids['A'], 'name' => 'A+', 'grade_point' => 4, 'min_percent' => 70],
            ['name' => 'B+', 'grade_point' => 3.25, 'min_percent' => 55],
            ['id' => $ids['B'], 'name' => 'B', 'grade_point' => 3, 'min_percent' => 50],
            ['id' => $ids['F'], 'name' => 'F', 'grade_point' => 0, 'min_percent' => 0],
        ];
        $data = $this->asUser($this->admin)->putJson("/api/grade-scales/{$scale['id']}", ['name' => 'GPA 5 (2026)', 'grades' => $grades])
            ->assertOk()->json('data');

        $this->assertSame('GPA 5 (2026)', $data['name']);
        $this->assertSame(['A', 'A+', 'B+', 'B', 'F'], array_column($data['grades'], 'name'));
        $this->assertSame([$ids['A+'], $ids['A']], [$data['grades'][0]['id'], $data['grades'][1]['id']]);
        $this->assertDatabaseMissing('grades', ['id' => $ids['A-']]);

        // A band id from another scale is refused.
        $other = $this->create([['name' => 'F', 'grade_point' => 0, 'min_percent' => 0]], 'Pass/fail')->json('data');
        $this->asUser($this->admin)->putJson("/api/grade-scales/{$scale['id']}", [
            'name' => 'GPA 5', 'grades' => [['id' => $other['grades'][0]['id'], 'name' => 'F', 'grade_point' => 0, 'min_percent' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['grades.0.id']);
    }

    public function test_only_grading_permission_changes_a_scale(): void
    {
        $scale = $this->create()->json('data');

        // Principal and Teacher have exam.view: they can read, not change.
        foreach (['Principal', 'Teacher'] as $role) {
            $user = $this->userWithRole($role);
            $this->asUser($user)->getJson('/api/grade-scales')->assertOk()->assertJsonPath('data.0.name', 'GPA 5');
            $this->asUser($user)->putJson("/api/grade-scales/{$scale['id']}", ['name' => 'Hacked', 'grades' => self::STANDARD])->assertForbidden();
            $this->asUser($user)->postJson('/api/grade-scales', ['name' => 'Mine', 'grades' => self::STANDARD])->assertForbidden();
            $this->asUser($user)->deleteJson("/api/grade-scales/{$scale['id']}")->assertForbidden();
        }
        $this->asUser($this->userWithRole('Parent'))->getJson('/api/grade-scales')->assertForbidden();
    }

    public function test_a_scale_used_by_an_exam_cannot_be_deleted(): void
    {
        $used = $this->create()->json('data');
        $unused = $this->create(name: 'Spare')->json('data');
        $exam = new Exam(['name' => 'Annual', 'type' => 'annual', 'grade_scale_id' => $used['id'], 'start_date' => '2026-11-01']);
        $exam->academic_year_id = $this->y2026->id;
        $exam->save();

        $this->asUser($this->admin)->deleteJson("/api/grade-scales/{$used['id']}")
            ->assertStatus(409)->assertJsonPath('message', 'This grade scale is used by an exam and cannot be deleted.');
        $this->asUser($this->admin)->deleteJson("/api/grade-scales/{$unused['id']}")->assertNoContent();
        $this->assertDatabaseMissing('grades', ['grade_scale_id' => $unused['id']]);
    }
}
