<?php

namespace App\Actions\Fee;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeFrequency;
use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicesJob;
use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeeInvoiceRun;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\SchoolClock;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Monthly invoices for a class or section (spec §18). queue() checks the
 * request and queues a run (spec §61); process() — run by
 * GenerateInvoicesJob — gives each actively enrolled student one invoice
 * for the month, built from the class's active fee structures for the year:
 *
 *  - monthly fees always (or the chosen ones, when fees are chosen);
 *  - one-time fees once per year: on the first invoice that includes them;
 *  - exam fees only when chosen.
 *
 * total = Σ lines and due = total (nothing paid yet), all server-side in
 * hundredths. A student who already has a (non-void) invoice for the month
 * is skipped, so re-running is safe; the unique index backs this up.
 */
class GenerateInvoices
{
    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function queue(User $user, array $data): FeeInvoiceRun
    {
        $year = isset($data['academic_year_id']) ? AcademicYear::findOrFail($data['academic_year_id']) : AcademicYear::current();
        if ($year === null) {
            $this->fail('academic_year_id', 'There is no current academic year; choose one.');
        }
        $class = SchoolClass::findOrFail($data['class_id']);
        $section = isset($data['section_id']) ? Section::findOrFail($data['section_id']) : null;
        if ($section !== null && $section->class_id !== $class->id) {
            $this->fail('section_id', "That section isn't part of {$class->name}.");
        }

        $period = $data['period'];
        $first = Carbon::createFromFormat('Y-m-d', "{$period}-01")->startOfDay();
        if ($first->copy()->endOfMonth()->toDateString() < $year->start_date->toDateString() || $first->toDateString() > $year->end_date->toDateString()) {
            $this->fail('period', "The month must fall within {$year->name}.");
        }
        $dueDate = $data['due_date'] ?? $first->copy()->endOfMonth()->toDateString();
        if ($dueDate < $first->toDateString()) {
            $this->fail('due_date', 'The due date can\'t be before the month starts.');
        }

        $structures = $this->structures($year->id, $class->id);
        if ($structures->isEmpty()) {
            $this->fail('class_id', "{$class->name} has no active fees set for {$year->name}.");
        }
        $chosen = isset($data['fee_type_ids']) ? array_values(array_map('intval', $data['fee_type_ids'])) : null;
        if ($chosen !== null) {
            $unknown = array_diff($chosen, $structures->pluck('fee_type_id')->all());
            if ($unknown !== [] || $chosen === []) {
                $this->fail('fee_type_ids', "Choose fees that are set for {$class->name} in {$year->name}.");
            }
        }

        // One run per class and month at a time (a section run counts too).
        // Runs left queued or running for over 15 minutes don't block: the
        // worker was down or died, and the per-student locks keep a rerun safe.
        $busy = FeeInvoiceRun::query()
            ->where('academic_year_id', $year->id)->where('class_id', $class->id)->where('period', $period)
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();
        abort_if($busy, 409, "Invoices for {$class->name}, ".$first->format('F Y').' are already being generated. Wait for that run to finish.');

        $run = (new FeeInvoiceRun)->forceFill([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section?->id,
            'period' => $period,
            'due_date' => $dueDate,
            'fee_type_ids' => $chosen,
            'status' => 'queued',
            'requested_by' => $user->id,
        ]);
        $run->save();
        Audit::record($user, 'fees.generation_requested', 'fees', $run, new: [
            'class_id' => $class->id,
            'section_id' => $section?->id,
            'period' => $period,
            'fee_type_ids' => $chosen,
        ]);

        GenerateInvoicesJob::dispatch($run->id);

        return $run->refresh();
    }

    /** Work through a queued run. Each student is its own transaction. */
    public function process(FeeInvoiceRun $run): void
    {
        $run->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $created = 0;
        $total = 0;

        try {
            $structures = $this->structures($run->academic_year_id, $run->class_id);
            $enrollments = StudentEnrollment::query()
                ->with('student')
                ->where('academic_year_id', $run->academic_year_id)
                ->where('class_id', $run->class_id)
                ->when($run->section_id, fn ($q, int $id) => $q->where('section_id', $id))
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('student', fn ($q) => $q->withoutTrashed())
                ->orderBy('section_id')->orderBy('roll')
                ->get();

            foreach ($enrollments as $enrollment) {
                try {
                    // Deadlocks between concurrent runs are retried.
                    $invoice = DB::transaction(fn () => $this->invoiceFor($run, $enrollment, $structures), attempts: 3);
                } catch (UniqueConstraintViolationException $e) {
                    // Only a live invoice for the month is a "duplicate"; any other
                    // clash (e.g. an invoice number) is a real failure.
                    if (! $this->invoiced($run, $enrollment)) {
                        throw $e;
                    }
                    $invoice = null;
                }
                if ($invoice !== null) {
                    $created++;
                    $total += Money::cents($invoice->total);
                }
            }

            $run->forceFill([
                'status' => 'completed',
                'created' => $created,
                'skipped' => $enrollments->count() - $created,
                'total' => Money::decimal($total),
                'finished_at' => now(),
            ])->save();

            Audit::record($run->requestedBy, 'fees.invoices_generated', 'fees', $run, new: [
                'class_id' => $run->class_id,
                'section_id' => $run->section_id,
                'period' => $run->period,
                'created' => $created,
                'total' => Money::decimal($total),
            ]);
        } catch (Throwable $e) {
            $this->failed($run, $created, $total);

            throw $e;
        }
    }

    /**
     * Record a run that stopped: what it created before stopping (those
     * invoices stand) and an audit entry. The exception itself goes to the
     * log, not to the screen.
     */
    public function failed(FeeInvoiceRun $run, ?int $created = null, int $totalCents = 0): void
    {
        $created ??= FeeInvoice::query()->where('fee_invoice_run_id', $run->id)->count();
        if ($totalCents === 0 && $created > 0) {
            $totalCents = Money::cents(FeeInvoice::query()->where('fee_invoice_run_id', $run->id)->sum('total'));
        }
        $run->forceFill([
            'status' => 'failed',
            'created' => $created,
            'total' => Money::decimal($totalCents),
            'error' => 'Generation stopped partway; the invoices already created stand. Re-run it to bill the rest (see the server log for details).',
            'finished_at' => now(),
        ])->save();

        Audit::record($run->requestedBy, 'fees.invoices_generation_failed', 'fees', $run, new: [
            'class_id' => $run->class_id,
            'section_id' => $run->section_id,
            'period' => $run->period,
            'created' => $created,
            'total' => Money::decimal($totalCents),
        ]);
    }

    private function invoiced(FeeInvoiceRun $run, StudentEnrollment $enrollment): bool
    {
        return FeeInvoice::query()->live()
            ->where('student_id', $enrollment->student_id)
            ->where('academic_year_id', $run->academic_year_id)
            ->where('period', $run->period)
            ->exists();
    }

    /**
     * @param  Collection<int, FeeStructure>  $structures
     */
    private function invoiceFor(FeeInvoiceRun $run, StudentEnrollment $enrollment, Collection $structures): ?FeeInvoice
    {
        // The student's row lock serialises everything billed to them, so two
        // runs can't both add a once-a-year fee or race on the same month.
        Student::withTrashed()->whereKey($enrollment->student_id)->lockForUpdate()->first();

        $already = FeeInvoice::query()->live()
            ->where('student_id', $enrollment->student_id)
            ->where('academic_year_id', $run->academic_year_id)
            ->where('period', $run->period)
            ->lockForUpdate()
            ->exists();
        if ($already) {
            return null;
        }

        // One-time fees already on one of the student's invoices this year.
        $billedOnce = FeeInvoiceItem::query()
            ->whereHas('invoice', fn ($q) => $q->live()->where('student_id', $enrollment->student_id)->where('academic_year_id', $run->academic_year_id))
            ->distinct()->pluck('fee_type_id')->all();

        $chosen = $run->fee_type_ids;
        $lines = $structures->filter(fn (FeeStructure $s) => match ($s->frequency) {
            FeeFrequency::Monthly => $chosen === null || in_array($s->fee_type_id, $chosen, true),
            FeeFrequency::OneTime => ! in_array($s->fee_type_id, $billedOnce, true) && ($chosen === null || in_array($s->fee_type_id, $chosen, true)),
            FeeFrequency::Exam => $chosen !== null && in_array($s->fee_type_id, $chosen, true),
        });
        if ($lines->isEmpty()) {
            return null;
        }

        $total = $lines->sum(fn (FeeStructure $s) => Money::cents($s->amount));
        $year = (int) SchoolClock::today()->format('Y');
        $month = Carbon::createFromFormat('Y-m-d', "{$run->period}-01")->format('F Y');

        $invoice = (new FeeInvoice)->forceFill([
            'invoice_no' => sprintf('INV-%d-%05d', $year, Sequence::next("fee_invoice:{$year}")),
            'student_id' => $enrollment->student_id,
            'student_enrollment_id' => $enrollment->id,
            'academic_year_id' => $run->academic_year_id,
            'class_id' => $run->class_id,
            'fee_invoice_run_id' => $run->id,
            'period' => $run->period,
            'due_date' => $run->due_date->toDateString(),
            'total' => Money::decimal($total),
            'paid' => '0.00',
            'due' => Money::decimal($total),
            'status' => InvoiceStatus::Unpaid,
            'issued_at' => now(),
            'issued_by' => $run->requested_by,
        ]);
        $invoice->save();

        foreach ($lines as $structure) {
            $invoice->items()->make()->forceFill([
                'fee_type_id' => $structure->fee_type_id,
                'fee_structure_id' => $structure->id,
                'description' => $structure->frequency === FeeFrequency::OneTime
                    ? "{$structure->feeType->name} ({$run->academicYear->name})"
                    : "{$structure->feeType->name} — {$month}",
                'amount' => Money::format($structure->amount),
            ])->save();
        }

        return $invoice;
    }

    /**
     * The class's active fees for the year (of active fee types).
     *
     * @return Collection<int, FeeStructure>
     */
    private function structures(int $yearId, int $classId): Collection
    {
        return FeeStructure::query()->with('feeType')
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->whereHas('feeType', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->get();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
