<?php

namespace Tests\Feature\Student;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentGuardianTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function student(string $name = 'Nusrat Jahan', string $studentId = '10000001'): Student
    {
        return Student::create([
            'name' => $name, 'dob' => '2014-03-15', 'gender' => 'female',
            'admission_date' => '2026-01-10', 'student_id' => $studentId, 'admission_no' => "2026-{$studentId}",
        ]);
    }

    private function guardian(string $name, string $phone = '01711000000'): Guardian
    {
        return Guardian::create(['name' => $name, 'phone' => $phone]);
    }

    private function link(Student $student, Guardian $guardian, string $relationship, bool $primary = false)
    {
        return $this->asUser($this->admin)->postJson("/api/students/{$student->id}/guardians", [
            'guardian_id' => $guardian->id, 'relationship' => $relationship, 'is_primary' => $primary,
        ]);
    }

    /** @return array<string, bool> guardian name => is_primary */
    private function links(Student $student): array
    {
        return $student->guardians()->get()
            ->mapWithKeys(fn (Guardian $g) => [$g->name => (bool) $g->pivot->is_primary])
            ->all();
    }

    public function test_the_first_guardian_becomes_primary_and_another_can_take_over(): void
    {
        $student = $this->student();
        $father = $this->guardian('Abdul Karim');
        $mother = $this->guardian('Rahima Begum');
        $uncle = $this->guardian('Jamal Uddin');

        $this->link($student, $father, 'father')
            ->assertCreated()
            ->assertJsonPath('data.guardians.0.name', 'Abdul Karim')
            ->assertJsonPath('data.guardians.0.is_primary', true);

        $this->link($student, $mother, 'mother')->assertCreated();
        $this->assertSame(['Abdul Karim' => true, 'Rahima Begum' => false], $this->links($student));

        $this->link($student, $uncle, 'uncle', primary: true)
            ->assertCreated()
            ->assertJsonPath('data.guardians.0.name', 'Jamal Uddin'); // primary listed first
        $this->assertSame(['Abdul Karim' => false, 'Rahima Begum' => false, 'Jamal Uddin' => true], $this->links($student));
    }

    public function test_a_guardian_can_have_many_children_and_a_child_many_guardians(): void
    {
        $karim = $this->guardian('Abdul Karim');
        $rahima = $this->guardian('Rahima Begum');
        $nusrat = $this->student('Nusrat Jahan', '10000001');
        $rafi = $this->student('Rafi Karim', '10000002');

        foreach ([$nusrat, $rafi] as $child) {
            $this->link($child, $karim, 'father')->assertCreated();
            $this->link($child, $rahima, 'mother')->assertCreated();
        }

        $this->asUser($this->admin)->getJson("/api/guardians/{$karim->id}")
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.relationship', 'father');
        $this->asUser($this->admin)->getJson('/api/guardians')
            ->assertJsonPath('data.0.students_count', 2);
        $this->assertSame(2, $nusrat->guardians()->count());
    }

    public function test_bad_links_are_rejected(): void
    {
        $student = $this->student();
        $father = $this->guardian('Abdul Karim');
        $this->link($student, $father, 'father')->assertCreated();

        $this->link($student, $father, 'father')
            ->assertStatus(422)
            ->assertJsonPath('errors.guardian_id.0', 'This guardian is already linked to the student.');

        $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/guardians", ['guardian_id' => 999, 'relationship' => 'cousin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guardian_id', 'relationship']);
    }

    public function test_relationship_and_primary_can_be_changed_but_the_primary_cannot_be_dropped(): void
    {
        $student = $this->student();
        $father = $this->guardian('Abdul Karim');
        $mother = $this->guardian('Rahima Begum');
        $this->link($student, $father, 'father');
        $this->link($student, $mother, 'other');

        $this->asUser($this->admin)
            ->putJson("/api/students/{$student->id}/guardians/{$mother->id}", ['relationship' => 'mother', 'is_primary' => true])
            ->assertOk();
        $this->assertSame(['Abdul Karim' => false, 'Rahima Begum' => true], $this->links($student));
        $this->assertSame('mother', $student->guardians()->find($mother->id)->pivot->relationship);

        $this->asUser($this->admin)
            ->putJson("/api/students/{$student->id}/guardians/{$mother->id}", ['relationship' => 'mother', 'is_primary' => false])
            ->assertStatus(422)
            ->assertJsonPath('errors.is_primary.0', 'A student always has one primary guardian. Make another guardian primary instead.');
    }

    public function test_unlinking_the_primary_promotes_the_longest_linked_guardian(): void
    {
        $student = $this->student();
        $father = $this->guardian('Abdul Karim');
        $mother = $this->guardian('Rahima Begum');
        $uncle = $this->guardian('Jamal Uddin');
        $this->link($student, $father, 'father');
        $this->link($student, $mother, 'mother');
        $this->link($student, $uncle, 'uncle');

        $this->asUser($this->admin)->deleteJson("/api/students/{$student->id}/guardians/{$father->id}")->assertOk();
        $this->assertSame(['Rahima Begum' => true, 'Jamal Uddin' => false], $this->links($student));
        $this->assertModelExists($father); // only the link goes

        $this->asUser($this->admin)->deleteJson("/api/students/{$student->id}/guardians/{$father->id}")->assertNotFound();
    }

    public function test_the_database_refuses_a_second_primary(): void
    {
        $student = $this->student();
        $this->link($student, $this->guardian('Abdul Karim'), 'father');

        $this->expectException(QueryException::class);
        DB::table('student_guardians')->insert([
            'student_id' => $student->id,
            'guardian_id' => $this->guardian('Rahima Begum')->id,
            'relationship' => 'mother',
            'is_primary' => true,
        ]);
    }

    public function test_a_guardian_linked_to_a_student_cannot_be_deleted(): void
    {
        $student = $this->student();
        $father = $this->guardian('Abdul Karim');
        $this->link($student, $father, 'father');

        $this->asUser($this->admin)->deleteJson("/api/guardians/{$father->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This guardian is linked to students and cannot be deleted.');
    }

    public function test_a_parent_sees_only_their_own_children_on_the_dashboard(): void
    {
        $karim = $this->guardian('Abdul Karim');
        $other = $this->guardian('Someone Else', '01800000000');
        $nusrat = $this->student('Nusrat Jahan', '10000001');
        $rafi = $this->student('Rafi Karim', '10000002');
        $stranger = $this->student('Not Mine', '10000003');
        $this->link($nusrat, $karim, 'father');
        $this->link($rafi, $karim, 'father');
        $this->link($stranger, $other, 'mother');
        $rafi->delete(); // archived children drop out

        $parent = User::factory()->create(['personable_type' => 'guardian', 'personable_id' => $karim->id]);
        $parent->assignRole('Parent');

        $children = collect($this->asUser($parent)->getJson('/api/dashboard')->assertOk()->json('data.widgets'))
            ->firstWhere('key', 'children');
        $this->assertSame('ready', $children['status']);
        $this->assertTrue($children['data']['linked']);
        $this->assertSame(['Nusrat Jahan'], array_column($children['data']['items'], 'name'));

        // A Parent login not linked to any guardian sees nobody.
        $unlinked = User::factory()->create();
        $unlinked->assignRole('Parent');
        $data = collect($this->asUser($unlinked)->getJson('/api/dashboard')->json('data.widgets'))->firstWhere('key', 'children')['data'];
        $this->assertSame(['linked' => false, 'items' => []], $data);
    }

    public function test_linking_needs_student_edit(): void
    {
        $student = $this->student();
        $father = $this->guardian('Abdul Karim');
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->asUser($principal)
            ->postJson("/api/students/{$student->id}/guardians", ['guardian_id' => $father->id, 'relationship' => 'father'])
            ->assertForbidden();

        $this->assertSame([], $this->links($student));
    }
}
