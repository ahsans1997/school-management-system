<?php

namespace Tests\Feature\Student;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function student(string $studentId = '10000001'): Student
    {
        return Student::create([
            'name' => 'Nusrat Jahan', 'dob' => '2014-03-15', 'gender' => 'female',
            'admission_date' => '2026-01-10', 'student_id' => $studentId, 'admission_no' => "2026-{$studentId}",
        ]);
    }

    /** A real temp file, so the MIME type is sniffed from its content (not the fake's declared type). */
    private function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_a_photo_is_stored_privately_and_served_through_a_signed_link(): void
    {
        $student = $this->student();

        $url = $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->image('face.png', 200, 240)])
            ->assertOk()
            ->json('data.photo_url');

        $path = $student->fresh()->photo_path;
        $this->assertStringStartsWith("students/{$student->id}/", $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        // The signed link works without a token, with safe headers…
        $this->flushHeaders();
        $this->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');

        // …but not tampered with, unsigned, or expired.
        $this->get($url.'x')->assertForbidden();
        $this->get(strtok($url, '?'))->assertForbidden();
        $this->travel(3)->hours();
        $this->get($url)->assertForbidden();
    }

    public function test_replacing_a_photo_deletes_the_old_file_and_it_can_be_removed(): void
    {
        $student = $this->student();
        $this->asUser($this->admin)->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')]);
        $first = $student->fresh()->photo_path;

        $this->asUser($this->admin)->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->image('b.jpg')]);
        Storage::disk('local')->assertMissing($first);

        $this->asUser($this->admin)->deleteJson("/api/students/{$student->id}/photo")
            ->assertOk()
            ->assertJsonPath('data.photo_url', null);
        $this->assertNull($student->fresh()->photo_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_photo_uploads_are_checked_by_content_and_size(): void
    {
        $student = $this->student();

        // A PHP script with an image extension: rejected on its sniffed content.
        $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/photo", ['photo' => $this->realUpload('face.png', '<?php echo "hi";')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->create('huge.jpg', 3000, 'image/jpeg')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->assertNull($student->fresh()->photo_path);
    }

    public function test_documents_are_uploaded_downloaded_by_original_name_and_deleted(): void
    {
        $student = $this->student();

        $doc = $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/documents", [
                'title' => 'Birth certificate',
                'file' => $this->realUpload('birth-cert.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Birth certificate')
            ->assertJsonPath('data.original_name', 'birth-cert.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->json('data');

        $this->asUser($this->admin)->getJson("/api/students/{$student->id}")
            ->assertJsonPath('data.documents.0.id', $doc['id']);

        $this->get($doc['download_url'])
            ->assertOk()
            ->assertDownload('birth-cert.pdf');

        $this->asUser($this->admin)->deleteJson("/api/students/{$student->id}/documents/{$doc['id']}")->assertNoContent();
        $this->assertDatabaseMissing('documents', ['id' => $doc['id']]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_awkward_original_filenames_are_made_safe_for_download(): void
    {
        $student = $this->student();

        $doc = $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/documents", [
                'title' => 'Report card',
                'file' => $this->realUpload("class\\six\nreport.pdf", "%PDF-1.4\ntrailer<<>>\n%%EOF"),
            ])
            ->assertCreated()
            // Symfony keeps only the part after the last "/" or "\"; we strip control characters.
            ->assertJsonPath('data.original_name', 'six_report.pdf')
            ->json('data');

        $this->get($doc['download_url'])->assertOk()->assertDownload('six_report.pdf');
    }

    public function test_unsigned_file_requests_cannot_probe_which_students_exist(): void
    {
        $student = $this->student();

        // Same answer for a real and a missing record: the signature is checked first.
        $this->get("/api/files/students/{$student->id}/photo")->assertForbidden();
        $this->get('/api/files/students/999/photo')->assertForbidden();
        $this->get('/api/files/documents/999')->assertForbidden();
    }

    public function test_documents_must_be_pdfs_or_images(): void
    {
        $student = $this->student();

        $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/documents", [
                'title' => 'Sneaky',
                'file' => $this->realUpload('report.pdf', '<html><script>alert(1)</script></html>'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, $student->documents()->count());
    }

    public function test_a_document_is_only_reachable_through_its_own_student(): void
    {
        $owner = $this->student('10000001');
        $other = $this->student('10000002');
        $doc = $owner->documents()->create([
            'title' => 'TC', 'path' => 'students/1/documents/tc.pdf', 'original_name' => 'tc.pdf',
            'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        $this->asUser($this->admin)
            ->deleteJson("/api/students/{$other->id}/documents/{$doc->id}")
            ->assertNotFound();

        $this->assertModelExists($doc);
    }

    public function test_archived_students_keep_their_files_viewable_but_frozen(): void
    {
        $student = $this->student();
        $this->asUser($this->admin)->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->image('a.png')]);
        $student->delete();

        $url = $this->asUser($this->admin)->getJson("/api/students/{$student->id}")->json('data.photo_url');
        $this->get($url)->assertOk();

        $this->asUser($this->admin)
            ->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->image('b.png')])
            ->assertNotFound();
    }

    public function test_file_changes_need_student_edit(): void
    {
        $student = $this->student();
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->asUser($principal)
            ->postJson("/api/students/{$student->id}/photo", ['photo' => UploadedFile::fake()->image('a.png')])
            ->assertForbidden();
        $this->asUser($principal)
            ->postJson("/api/students/{$student->id}/documents", ['title' => 'X', 'file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
