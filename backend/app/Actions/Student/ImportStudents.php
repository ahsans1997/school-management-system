<?php

namespace App\Actions\Student;

use App\Actions\Enrollment\Enrollments;
use App\Http\Requests\Student\StudentRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\SchoolClock;
use App\Support\Spreadsheet;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Exception\OpenSpoutException;

/**
 * Import students from a CSV/Excel sheet (spec §9). Every row is checked
 * first, with the same rules as the add-student form plus the enrollment
 * rules (class/section exist, roll free, seats left), and problems are
 * reported per row. Nothing is saved unless every row passes. Rows with a
 * class and section are enrolled in the current academic year in the same
 * pass. Synchronous: a school-sized file (≤ 1000 rows) takes seconds.
 */
class ImportStudents
{
    public const MAX_ROWS = 1000;

    /** Our field => accepted header spellings (lower-case, "_" for spaces/punctuation). */
    public const COLUMNS = [
        'student_id' => ['student_id', 'student_no', 'student_number'],
        'admission_no' => ['admission_no', 'admission_number'],
        'name' => ['name', 'student_name', 'full_name'],
        'dob' => ['dob', 'date_of_birth', 'birth_date'],
        'gender' => ['gender', 'sex'],
        'blood_group' => ['blood_group'],
        'phone' => ['phone', 'mobile', 'phone_number'],
        'address' => ['address'],
        'father_name' => ['father_name', 'fathers_name', 'father'],
        'mother_name' => ['mother_name', 'mothers_name', 'mother'],
        'admission_date' => ['admission_date', 'date_of_admission'],
        'previous_school' => ['previous_school'],
        'class' => ['class', 'class_name'],
        'section' => ['section', 'section_name'],
        'roll' => ['roll', 'roll_no', 'roll_number'],
    ];

    public const REQUIRED = ['name', 'dob', 'gender'];

    /** Readable column names for messages. */
    private const ATTRIBUTES = [
        'student_id' => 'student ID', 'admission_no' => 'admission no.', 'dob' => 'date of birth',
        'blood_group' => 'blood group', 'father_name' => "father's name", 'mother_name' => "mother's name",
        'admission_date' => 'admission date', 'previous_school' => 'previous school',
    ];

    public function __construct(
        private readonly CreateStudentAction $createStudent,
        private readonly Enrollments $enrollments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(UploadedFile $file, User $user, bool $dryRun): array
    {
        $format = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        try {
            $lines = Spreadsheet::read($file->getRealPath(), $format);
        } catch (OpenSpoutException) {
            $this->fail("This file can't be read as ".($format === 'xlsx' ? 'an Excel workbook.' : 'CSV.'));
        }
        if ($lines === []) {
            $this->fail('The file is empty.');
        }

        [$columns, $ignored] = $this->header(array_shift($lines)['cells']);
        if ($lines === []) {
            $this->fail('The file has a header row but no students.');
        }
        if (count($lines) > self::MAX_ROWS) {
            $this->fail('Import at most '.self::MAX_ROWS.' students at a time; this file has '.count($lines).'.');
        }

        $rows = array_map(fn (array $line) => ['line' => $line['line'], 'data' => $this->values($columns, $line['cells'])], $lines);
        $enrolling = array_filter($rows, fn (array $r) => $r['data']['class'] !== null || $r['data']['section'] !== null);
        abort_if($enrolling !== [] && ! $user->can('enrollment.create'), 403, 'Rows with a class and section enroll students, which needs permission to create enrollments.');
        $year = AcademicYear::current();
        if ($enrolling !== [] && $year === null) {
            $this->fail('Set a current academic year before importing students with a class and section.');
        }

        $rows = $this->check($rows, $year);

        $summary = ['dry_run' => $dryRun, 'rows' => count($rows), 'enrolling' => count($enrolling), 'ignored_columns' => $ignored];

        return $dryRun ? $summary : [...$summary, 'students' => $this->commit($rows, $year)];
    }

    /**
     * Column index => our field, plus the headers we don't recognise.
     *
     * @param  list<mixed>  $cells
     * @return array{0: array<int, string>, 1: list<string>}
     */
    private function header(array $cells): array
    {
        $aliases = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($names as $name) {
                $aliases[$name] = $field;
            }
        }

        $columns = [];
        $ignored = [];
        foreach ($cells as $index => $label) {
            $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', str_replace("'", '', mb_strtolower((string) $label))), '_');
            if (isset($aliases[$key]) && ! in_array($aliases[$key], $columns, true)) {
                $columns[$index] = $aliases[$key];
            } elseif ($key !== '') {
                $ignored[] = (string) $label;
            }
        }

        $missing = array_diff(self::REQUIRED, $columns);
        if ($missing !== []) {
            $names = array_map(fn ($f) => self::ATTRIBUTES[$f] ?? $f, $missing);
            $this->fail('The file needs these columns: '.implode(', ', $names).'. Download the template to see the layout.');
        }

        return [$columns, $ignored];
    }

    /**
     * One row's values by field, tidied: blanks become null, Excel dates and
     * numbers become text, labels like "Female" become stored values.
     *
     * @param  array<int, string>  $columns
     * @param  list<mixed>  $cells
     * @return array<string, string|null>
     */
    private function values(array $columns, array $cells): array
    {
        $data = array_fill_keys(array_keys(self::COLUMNS), null);
        foreach ($columns as $index => $field) {
            $value = Spreadsheet::dateCell($cells[$index] ?? null);
            if (is_float($value) && floor($value) === $value) {
                $value = (int) $value;
            }
            // A date column formatted as "General" holds Excel's day count.
            if (in_array($field, ['dob', 'admission_date'], true) && is_int($value)) {
                $value = Spreadsheet::excelSerialDate($value);
            }
            // Excel drops the leading 0 of a mobile number typed into a number cell.
            if ($field === 'phone' && is_int($value) && preg_match('/^1\d{9}$/', (string) $value) === 1) {
                $value = '0'.$value;
            }
            $value = is_scalar($value) ? trim((string) $value) : null;
            $data[$field] = $value === '' ? null : $value;
        }

        $data['gender'] = $data['gender'] === null ? null : match (mb_strtolower($data['gender'])) {
            'm' => 'male', 'f' => 'female', default => mb_strtolower($data['gender']),
        };
        $data['blood_group'] = $data['blood_group'] === null ? null : strtoupper(str_replace(' ', '', $data['blood_group']));
        foreach (['dob', 'admission_date'] as $field) {
            $data[$field] = $this->isoDate($data[$field]);
        }

        return $data;
    }

    /** Day-first dates (31/12/2014, 31-12-2014, 31.12.2014) become ISO; anything else is left for validation. */
    private function isoDate(?string $value): ?string
    {
        if ($value !== null && preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $value;
    }

    /**
     * Validate every row; throw a 422 listing each bad row, or return the
     * rows with their resolved section.
     *
     * @param  list<array{line: int, data: array<string, string|null>}>  $rows
     * @return list<array{line: int, data: array<string, string|null>, section: Section|null}>
     */
    private function check(array $rows, ?AcademicYear $year): array
    {
        $classes = SchoolClass::query()->with('sections')->get();
        $errors = [];
        $seen = ['student_id' => [], 'admission_no' => []];
        $rollsTaken = [];   // section id => [roll => true] (database + earlier rows)
        $seatsLeft = [];    // section id => free seats left

        foreach ($rows as $i => $row) {
            $data = $row['data'];
            $messages = Validator::make($data, [
                ...StudentRequest::fieldRules(null),
                // The form's date rules, but the format first so one bad date gives one message.
                'dob' => ['bail', 'required', 'date_format:Y-m-d', 'before:'.SchoolClock::date(), 'after:1950-01-01'],
                'admission_date' => ['bail', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.SchoolClock::date()],
                'roll' => ['nullable', 'integer', 'between:1,9999'],
            ], [
                ...StudentRequest::fieldMessages(),
                'dob.date_format' => 'Write the date of birth as a real date: YYYY-MM-DD or DD/MM/YYYY.',
                'admission_date.date_format' => 'Write the admission date as a real date: YYYY-MM-DD or DD/MM/YYYY.',
            ], self::ATTRIBUTES)->errors()->all();

            foreach (['student_id', 'admission_no'] as $field) {
                if ($data[$field] !== null) {
                    $key = mb_strtolower($data[$field]);
                    if (isset($seen[$field][$key])) {
                        $messages[] = 'The '.self::ATTRIBUTES[$field]." {$data[$field]} is also used on row {$seen[$field][$key]}.";
                    }
                    $seen[$field][$key] ??= $row['line'];
                }
            }

            $section = null;
            if ($data['class'] !== null || $data['section'] !== null) {
                [$section, $placeMessage] = $this->place($classes, $data['class'], $data['section']);
                if ($placeMessage !== null) {
                    $messages[] = $placeMessage;
                }
            }

            if ($section !== null && $year !== null) {
                $label = "{$section->schoolClass->name} {$section->name}";
                $rollsTaken[$section->id] ??= StudentEnrollment::query()
                    ->where('section_id', $section->id)->where('academic_year_id', $year->id)
                    ->pluck('roll')->flip()->map(fn () => true)->all();
                $seatsLeft[$section->id] ??= $section->capacity - Enrollments::seatsTaken($section, $year);

                if ($data['roll'] !== null && ctype_digit($data['roll'])) {
                    if (isset($rollsTaken[$section->id][(int) $data['roll']])) {
                        $messages[] = "Roll {$data['roll']} is already taken in {$label} for {$year->name}.";
                    }
                    $rollsTaken[$section->id][(int) $data['roll']] = true;
                }
                if ($seatsLeft[$section->id]-- <= 0) {
                    $messages[] = "{$label} is full ({$section->capacity} seats).";
                }
            }

            if ($messages !== []) {
                $errors[$row['line']] = $messages;
            }
            $rows[$i]['section'] = $section;
        }

        if ($errors !== []) {
            $this->rejectRows($errors);
        }

        return $rows;
    }

    /**
     * The section a row names, or a message saying what's wrong.
     *
     * @param  Collection<int, SchoolClass>  $classes
     * @return array{0: Section|null, 1: string|null}
     */
    private function place($classes, ?string $className, ?string $sectionName): array
    {
        if ($className === null) {
            return [null, 'Give the class as well as the section.'];
        }
        $wanted = mb_strtolower($className);
        $class = $classes->first(fn (SchoolClass $c) => mb_strtolower($c->name) === $wanted)
            ?? (ctype_digit($className) ? $classes->firstWhere('numeric_level', (int) $className) : null);
        if ($class === null) {
            return [null, "There's no class called “{$className}”."];
        }
        if ($sectionName === null) {
            return [null, 'Give the section as well as the class.'];
        }
        $section = $class->sections->first(fn (Section $s) => mb_strtolower($s->name) === mb_strtolower($sectionName));
        if ($section === null) {
            return [null, "{$class->name} has no section “{$sectionName}”."];
        }
        $section->setRelation('schoolClass', $class);

        return [$section, null];
    }

    /**
     * Save everything in one transaction; any failure undoes the whole file.
     * Rows with a typed-in roll are enrolled before rows that get the next
     * free roll, so the two can't collide.
     *
     * @param  list<array{line: int, data: array<string, string|null>, section: Section|null}>  $rows
     * @return list<array<string, mixed>>
     */
    private function commit(array $rows, ?AcademicYear $year): array
    {
        return DB::transaction(function () use ($rows, $year) {
            $students = [];
            foreach ($rows as $i => $row) {
                $fields = array_diff_key($row['data'], array_flip(['class', 'section', 'roll']));
                $students[$i] = $this->createStudent->execute(array_filter($fields, fn ($v) => $v !== null));
            }

            $order = array_keys($rows);
            usort($order, fn ($a, $b) => ($rows[$a]['data']['roll'] === null) <=> ($rows[$b]['data']['roll'] === null));
            $places = [];
            $errors = [];
            foreach ($order as $i) {
                $section = $rows[$i]['section'];
                if ($section === null || $year === null) {
                    continue;
                }
                try {
                    $roll = $rows[$i]['data']['roll'] === null ? null : (int) $rows[$i]['data']['roll'];
                    $places[$i] = $this->enrollments->enroll($students[$i], $year, $section, $roll);
                } catch (ValidationException $e) {
                    $errors[$rows[$i]['line']] = $e->validator->errors()->all();
                }
            }
            if ($errors !== []) {
                $this->rejectRows($errors); // rolls the whole transaction back
            }

            return array_map(fn (int $i) => [
                'row' => $rows[$i]['line'],
                'id' => $students[$i]->id,
                'name' => $students[$i]->name,
                'student_id' => $students[$i]->student_id,
                'admission_no' => $students[$i]->admission_no,
                'class' => isset($places[$i]) ? $rows[$i]['section']->schoolClass->name : null,
                'section' => isset($places[$i]) ? $rows[$i]['section']->name : null,
                'roll' => isset($places[$i]) ? $places[$i]->roll : null,
            ], array_keys($rows));
        });
    }

    /**
     * @param  array<int, list<string>>  $errors  line => messages
     */
    private function rejectRows(array $errors): never
    {
        ksort($errors);
        $count = count($errors);

        throw new HttpResponseException(response()->json([
            'message' => ($count === 1 ? '1 row has problems' : "{$count} rows have problems").'. Nothing was imported.',
            'errors' => collect($errors)->mapWithKeys(fn ($messages, $line) => ["rows.{$line}" => $messages])->all(),
            'rows' => collect($errors)->map(fn ($messages, $line) => ['row' => $line, 'errors' => $messages])->values()->all(),
        ], 422));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
