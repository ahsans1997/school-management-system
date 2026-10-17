<?php

namespace Tests\Feature\Student;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class StudentImportExportTest extends EnrollmentTestCase
{
    private const HEADER = "Name,Date of birth,Gender,Student ID,Father's name,Phone,Class,Section,Roll\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-06 10:00');
    }

    private function csv(string $rows, string $header = self::HEADER): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.csv', $header.$rows);
    }

    private function import(UploadedFile $file, bool $dryRun = false, ?User $as = null): TestResponse
    {
        return $this->asUser($as ?? $this->admin)->post('/api/students/import', ['file' => $file, 'dry_run' => $dryRun ? '1' : '0'], ['Accept' => 'application/json']);
    }

    private function download(string $url, ?User $as = null): TestResponse
    {
        return $this->asUser($as ?? $this->admin)->get($url, ['Accept' => 'application/json']);
    }

    /** Body of a streamed file response. */
    private function body(TestResponse $response): string
    {
        return file_get_contents($response->baseResponse->getFile()->getPathname());
    }

    public function test_a_file_is_checked_first_then_imported_and_enrolled_in_one_go(): void
    {
        $rows = "Nusrat Jahan,10/05/2014,Female,,Abdul Karim,01711000001,Class 6,a,\n"
            ."Rafi Karim,2014-02-01,m,MIG-001,,,Class 6,A,1\n"
            ."Tania Akter,2013-12-24,female,,,,,,\n";

        $check = $this->import($this->csv($rows), dryRun: true)->assertOk()->json('data');
        $this->assertSame([true, 3, 2], [$check['dry_run'], $check['rows'], $check['enrolling']]);
        $this->assertSame(0, Student::count());

        $result = $this->import($this->csv($rows))->assertCreated()->json('data.students');

        $nusrat = Student::query()->where('name', 'Nusrat Jahan')->sole();
        $this->assertSame(['2014-05-10', 'female', 'Abdul Karim'], [$nusrat->dob->toDateString(), $nusrat->gender->value, $nusrat->father_name]);
        $this->assertSame('MIG-001', Student::query()->where('name', 'Rafi Karim')->value('student_id')); // typed-in ID kept
        // The typed-in roll 1 goes to Rafi; Nusrat (no roll) gets the next one.
        $this->assertSame([2, 1, null], array_column($result, 'roll'));
        $this->assertSame(['Class 6', 'Class 6', null], array_column($result, 'class'));
        $this->assertSame(2, StudentEnrollment::query()->where('section_id', $this->sixA->id)->count());
        $this->assertSame(3, Student::count());
    }

    public function test_every_bad_row_is_reported_and_nothing_is_saved(): void
    {
        $taken = $this->student('Already Here');
        $this->enroll($taken, $this->sixB, roll: 4)->assertCreated();

        $rows = "Ok Student,2014-01-01,male,,,,,,\n"                       // line 2: fine
            .",2014-01-01,male,,,,,,\n"                                     // 3: no name
            ."Bad Date,31/02/2014,male,,,,,,\n"                             // 4: no such date
            ."Bad Gender,2014-01-01,boy,,,,,,\n"                            // 5
            ."No Class,2014-01-01,male,,,,Class 11,A,\n"                    // 6
            ."No Section,2014-01-01,male,,,,Class 7,B,\n"                   // 7
            ."Dup One,2014-01-01,male,X-1,,,,,\n"                           // 8: fine on its own
            ."Dup Two,2014-01-01,male,x-1,,,,,\n"                           // 9: same ID as line 8
            ."Taken Roll,2014-01-01,male,,,,Class 6,B,4\n"                  // 10
            ."Existing ID,2014-01-01,male,{$taken->student_id},,,,,\n"      // 11
            ."Half Place,2014-01-01,male,,,,,A,\n";                         // 12

        $response = $this->import($this->csv($rows))->assertUnprocessable();

        $this->assertSame('9 rows have problems. Nothing was imported.', $response->json('message'));
        $this->assertSame([3, 4, 5, 6, 7, 9, 10, 11, 12], array_column($response->json('rows'), 'row'));
        $errors = $response->json('errors');
        $this->assertStringContainsString('name', $errors['rows.3'][0]);
        $this->assertSame(['Write the date of birth as a real date: YYYY-MM-DD or DD/MM/YYYY.'], $errors['rows.4']); // one clear message
        $this->assertSame("There's no class called “Class 11”.", $errors['rows.6'][0]);
        $this->assertSame('Class 7 has no section “B”.', $errors['rows.7'][0]);
        $this->assertSame('The student ID x-1 is also used on row 8.', $errors['rows.9'][0]);
        $this->assertSame('Roll 4 is already taken in Class 6 B for 2026.', $errors['rows.10'][0]);
        $this->assertSame('Give the class as well as the section.', $errors['rows.12'][0]);
        $this->assertSame(1, Student::count()); // only the student made before the import
    }

    public function test_a_full_section_and_missing_columns_are_refused(): void
    {
        // Class 6 A seats 3 in the fixtures.
        $rows = implode('', array_map(fn ($i) => "Student {$i},2014-01-01,male,,,,Class 6,A,\n", range(1, 4)));
        $this->import($this->csv($rows))->assertUnprocessable()
            ->assertJsonPath('errors', ['rows.5' => ['Class 6 A is full (3 seats).']]);

        $this->import($this->csv("Nusrat\n", "Name\n"))->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'The file needs these columns: date of birth, gender. Download the template to see the layout.');
        $this->import(UploadedFile::fake()->create('students.pdf', 10, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors(['file']);
        $this->assertSame(0, Student::count());
    }

    public function test_an_excel_file_imports_dates_and_phone_numbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Name', 'DOB', 'Gender', 'Mobile', 'Class', 'Section', 'Roll', 'Notes']));
        // A date cell formatted as a date, a date left as Excel's day count
        // (column formatted "General"), and a mobile number typed as a number
        // (Excel drops its 0).
        $dateStyle = (new Style)->withFormat('yyyy-mm-dd');
        $writer->addRow(new Row([
            new StringCell('Anika Rahman'), new DateTimeCell(new DateTimeImmutable('2014-03-15'), $dateStyle), new StringCell('Female'),
            new NumericCell(1711000002), new StringCell('Class 7'), new StringCell('A'), new NumericCell(3), new StringCell('ignored'),
        ]));
        $writer->addRow(Row::fromValues(['Tania Akter', 41713, 'f', null, null, null, null, null]));
        $writer->close();

        $data = $this->import(new UploadedFile($path, 'students.xlsx', null, null, true))->assertCreated()->json('data');

        $this->assertSame(['Notes'], $data['ignored_columns']);
        $anika = Student::query()->where('name', 'Anika Rahman')->sole();
        $this->assertSame(['2014-03-15', '01711000002'], [$anika->dob->toDateString(), $anika->phone]);
        $this->assertSame([7, 3], [$anika->enrollments()->first()->schoolClass->numeric_level, $anika->enrollments()->first()->roll]);
        $this->assertSame('2014-03-15', Student::query()->where('name', 'Tania Akter')->sole()->dob->toDateString());
    }

    public function test_importing_needs_create_permissions(): void
    {
        // Receptionists may add students but not enroll them.
        $receptionist = $this->userWithRole('Receptionist');
        $this->import($this->csv("Nusrat,2014-01-01,female,,,,Class 6,A,\n"), as: $receptionist)->assertForbidden();
        $this->import($this->csv("Nusrat,2014-01-01,female,,,,,,\n"), as: $receptionist)->assertCreated();

        $this->import($this->csv("Rafi,2014-01-01,male,,,,,,\n"), as: $this->userWithRole('Teacher'))->assertForbidden();
        $this->download('/api/students/import/template', $this->userWithRole('Teacher'))->assertForbidden();
    }

    public function test_the_export_lists_each_students_place_and_honours_filters(): void
    {
        $nusrat = $this->student('Nusrat');
        $this->enroll($nusrat, $this->sixA, roll: 2)->assertCreated();
        $rafi = $this->student('Rafi');
        $this->enroll($rafi, $this->sixA, roll: 1)->assertCreated();
        $anika = $this->student('Anika');
        $this->enroll($anika, $this->sevenA)->assertCreated();
        $this->student('Tania'); // not enrolled
        $guardian = Guardian::create(['name' => 'Rahima', 'phone' => '01922222222']);
        $nusrat->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);
        // Text a spreadsheet would run as a formula is neutralised; phone numbers stay as they are.
        $nusrat->update(['address' => '=HYPERLINK("http://evil.test","x")', 'phone' => '+8801711000001']);

        $response = $this->download('/api/students/export')->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('students-2026.csv', $response->headers->get('Content-Disposition'));
        $csv = $this->body($response);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // BOM, so Excel reads Bangla names as UTF-8
        $lines = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));

        $this->assertSame('Student ID', $lines[0][0]);
        $this->assertSame(['Rafi', 'Nusrat', 'Anika', 'Tania'], array_column(array_slice($lines, 1), 2));
        $nusratRow = array_combine($lines[0], $lines[2]);
        $this->assertSame(['Class 6', 'A', '2', 'Active', 'Rahima', '01922222222'], [
            $nusratRow['Class'], $nusratRow['Section'], $nusratRow['Roll'], $nusratRow['Enrollment status'],
            $nusratRow['Primary guardian'], $nusratRow['Guardian phone'],
        ]);
        $this->assertSame('\'=HYPERLINK("http://evil.test","x")', $nusratRow['Address']);
        $this->assertSame('+8801711000001', $nusratRow['Phone']);
        $this->assertSame('Not enrolled', array_combine($lines[0], $lines[4])['Enrollment status']);

        $names = fn (string $query) => array_column(array_slice(array_map('str_getcsv', array_filter(explode("\n", substr($this->body($this->download('/api/students/export'.$query)->assertOk()), 3)))), 1), 2);
        $this->assertSame(['Rafi', 'Nusrat'], $names("?section_id={$this->sixA->id}"));
        $this->assertSame(['Anika'], $names("?class_id={$this->seven->id}"));
        $this->assertSame(['Tania'], $names('?status=not_enrolled'));
    }

    public function test_the_export_and_template_come_as_excel_too(): void
    {
        $this->student('Nusrat');

        $response = $this->download('/api/students/export?format=xlsx')->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $reader = new XlsxReader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        $this->assertSame(['Student ID', 'Nusrat'], [$rows[0][0], $rows[1][2]]);

        $template = $this->body($this->download('/api/students/import/template')->assertOk());
        $this->assertStringContainsString('Name,Gender,"Date of birth"', $template);
    }

    public function test_exporting_needs_the_export_permission(): void
    {
        $this->download('/api/students/export', $this->userWithRole('Principal'))->assertForbidden();
        $this->download('/api/students/export', $this->userWithRole('Teacher'))->assertForbidden();
    }
}
