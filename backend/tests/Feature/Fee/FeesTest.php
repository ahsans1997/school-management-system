<?php

namespace Tests\Feature\Fee;

use App\Actions\Fee\GenerateInvoices;
use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicesJob;
use App\Models\AuditLog;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRun;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class FeesTest extends EnrollmentTestCase
{
    private User $accountant;

    /** @var array<string, FeeType> */
    private array $types = [];

    private Student $rahim;

    private Student $karim;

    private Student $sumi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-05 10:00');
        $this->accountant = $this->userWithRole('Accountant');

        foreach ([['Tuition', 'tuition'], ['Admission', 'admission'], ['Exam fee', 'exam'], ['Transport', 'transport']] as [$name, $category]) {
            $this->types[$name] = FeeType::create(['name' => $name, 'category' => $category]);
        }
        // Class 6, 2026: tuition monthly, admission once, exam when chosen; transport is switched off.
        foreach ([['Tuition', '1500', 'monthly', true], ['Admission', '5000', 'one_time', true], ['Exam fee', '300', 'exam', true], ['Transport', '500', 'monthly', false]] as [$type, $amount, $frequency, $active]) {
            FeeStructure::create([
                'fee_type_id' => $this->types[$type]->id, 'class_id' => $this->six->id, 'academic_year_id' => $this->y2026->id,
                'amount' => $amount, 'frequency' => $frequency, 'is_active' => $active,
            ]);
        }

        $this->rahim = $this->student('Rahim');
        $this->karim = $this->student('Karim');
        $this->sumi = $this->student('Sumi');
        $this->enroll($this->rahim, $this->sixA)->assertCreated();
        $this->enroll($this->karim, $this->sixA)->assertCreated();
        $this->enroll($this->sumi, $this->sixB)->assertCreated();
    }

    private function generate(array $data, ?User $as = null): TestResponse
    {
        return $this->asUser($as ?? $this->accountant)->postJson('/api/fee-invoices/generate', ['class_id' => $this->six->id, ...$data]);
    }

    private function invoiceOf(Student $student, string $period): FeeInvoice
    {
        return FeeInvoice::query()->live()->where('student_id', $student->id)->where('period', $period)->sole();
    }

    public function test_fee_types_and_structures_are_set_per_class_and_year(): void
    {
        $this->asUser($this->accountant)->postJson('/api/fee-types', ['name' => 'Library', 'code' => 'lib', 'category' => 'library'])
            ->assertCreated()->assertJsonPath('data.code', 'LIB')->assertJsonPath('data.is_active', true);
        $this->assertSame('library', AuditLog::query()->where('action', 'fees.type_created')->sole()->new_values['category']);
        $this->asUser($this->accountant)->postJson('/api/fee-types', ['name' => 'Library', 'category' => 'library'])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $structure = fn (array $data) => $this->asUser($this->accountant)->postJson('/api/fee-structures', [
            'fee_type_id' => $this->types['Tuition']->id, 'class_id' => $this->seven->id, 'academic_year_id' => $this->y2026->id,
            'amount' => '1800', 'frequency' => 'monthly', ...$data,
        ]);
        $created = $structure([])->assertCreated()->assertJsonPath('data.amount', '1800.00')->assertJsonPath('data.class.name', 'Class 7')->json('data');
        $structure([])->assertUnprocessable()->assertJsonValidationErrors(['fee_type_id']); // once per class + year
        $structure(['fee_type_id' => $this->types['Exam fee']->id, 'amount' => 0])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
        $structure(['fee_type_id' => $this->types['Exam fee']->id, 'amount' => '10.555'])->assertUnprocessable()->assertJsonValidationErrors(['amount']);

        // Editing changes the amount, not the class or type; it's audited.
        $this->asUser($this->accountant)->putJson("/api/fee-structures/{$created['id']}", ['amount' => '1850.50', 'frequency' => 'monthly', 'class_id' => $this->six->id])
            ->assertOk()->assertJsonPath('data.amount', '1850.50')->assertJsonPath('data.class.name', 'Class 7');
        $log = AuditLog::query()->where('action', 'fees.structure_updated')->sole();
        $this->assertSame(['1800.00', '1850.50', $this->accountant->id], [$log->old_values['amount'], $log->new_values['amount'], $log->user_id]);

        $list = $this->asUser($this->accountant)->getJson("/api/fee-structures?academic_year_id={$this->y2026->id}&class_id={$this->six->id}")->assertOk()->json('data');
        $this->assertSame(['Admission', 'Exam fee', 'Transport', 'Tuition'], array_column(array_column($list, 'fee_type'), 'name'));

        // Finance staff get the class/year pickers through fee.view (they hold no class.view).
        $options = $this->asUser($this->accountant)->getJson('/api/fees/options')->assertOk()->json('data');
        $this->assertSame(['Class 6', 'Class 7'], array_column($options['classes'], 'name'));
        $this->assertSame(['A', 'B'], array_column($options['classes'][0]['sections'], 'name'));
        $this->assertSame([true, 'BDT'], [collect($options['academic_years'])->firstWhere('name', '2026')['is_current'], $options['currency']]);

        // Principal reads, can't write; teachers have no fee access.
        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson('/api/fee-structures')->assertOk();
        $structure(['fee_type_id' => $this->types['Exam fee']->id])->assertCreated();
        $this->asUser($principal)->postJson('/api/fee-types', ['name' => 'Hostel', 'category' => 'hostel'])->assertForbidden();
        $this->asUser($this->userWithRole('Teacher'))->getJson('/api/fee-structures')->assertForbidden();

        // A structure that was billed can't be deleted (turn it off instead); an unused one can.
        $this->generate(['period' => '2026-03'])->assertAccepted();
        $tuition6 = FeeStructure::query()->where('class_id', $this->six->id)->where('fee_type_id', $this->types['Tuition']->id)->sole();
        $this->asUser($this->accountant)->deleteJson("/api/fee-structures/{$tuition6->id}")->assertConflict();
        $this->asUser($this->accountant)->deleteJson("/api/fee-structures/{$created['id']}")->assertNoContent();
        $this->asUser($this->accountant)->deleteJson("/api/fee-types/{$this->types['Tuition']->id}")->assertConflict();
    }

    public function test_invoices_are_generated_in_the_background_with_server_computed_totals(): void
    {
        // The request only queues a run.
        $queue = Queue::getFacadeRoot();
        Queue::fake();
        $run = $this->generate(['period' => '2026-03', 'due_date' => '2026-03-15', 'total' => 1])->assertAccepted()
            ->assertJsonPath('data.status', 'queued')->assertJsonPath('data.class.name', 'Class 6')->json('data');
        Queue::assertPushed(GenerateInvoicesJob::class, fn (GenerateInvoicesJob $job) => $job->runId === $run['id']);
        $this->assertSame(0, FeeInvoice::query()->count());

        // The job builds each student's invoice: tuition + the one-time admission fee; no exam fee, no inactive transport.
        (new GenerateInvoicesJob($run['id']))->handle(app(GenerateInvoices::class));
        Queue::swap($queue); // from here on the queue runs inline, as in all tests
        $this->asUser($this->accountant)->getJson("/api/fee-invoices/runs/{$run['id']}")->assertOk()
            ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.created', 3)->assertJsonPath('data.total', '19500.00');

        $invoice = $this->asUser($this->accountant)->getJson('/api/fee-invoices/'.$this->invoiceOf($this->rahim, '2026-03')->id)->assertOk()->json();
        $this->assertSame(['Tuition — March 2026', 'Admission (2026)'], array_column($invoice['data']['items'], 'description'));
        $this->assertSame(['6500.00', '0.00', '6500.00', 'unpaid', '2026-03-15'], [
            $invoice['data']['total'], $invoice['data']['paid'], $invoice['data']['due'], $invoice['data']['status'], $invoice['data']['due_date'],
        ]);
        $this->assertMatchesRegularExpression('/^INV-2026-\d{5}$/', $invoice['data']['invoice_no']);
        $this->assertSame(['A', 'BDT'], [$invoice['data']['section']['name'], $invoice['currency']]);

        // Next month (the queue runs inline in tests): the admission fee isn't billed again.
        $this->generate(['period' => '2026-04'])->assertAccepted()->assertJsonPath('data.status', 'completed');
        $this->assertSame('1500.00', $this->invoiceOf($this->rahim, '2026-04')->total);
        // Exam fees only when chosen, and then only the chosen fees.
        $this->generate(['period' => '2026-05', 'fee_type_ids' => [$this->types['Tuition']->id, $this->types['Exam fee']->id]])->assertAccepted();
        $this->assertSame('1800.00', $this->invoiceOf($this->rahim, '2026-05')->total);

        // Re-running a month skips everyone already invoiced; the unique index backs it up.
        $this->generate(['period' => '2026-03'])->assertAccepted()->assertJsonPath('data.created', 0)->assertJsonPath('data.skipped', 3);
        // One section only; a student who left isn't billed.
        StudentEnrollment::query()->where('student_id', $this->karim->id)->update(['status' => 'left', 'ended_on' => '2026-03-31']);
        $this->generate(['period' => '2026-06', 'section_id' => $this->sixA->id])->assertJsonPath('data.created', 1);
        $this->assertSame(1, FeeInvoice::query()->where('period', '2026-06')->count());

        // Refused up front: a month outside the year, another class's section, a class with no fees, fees it doesn't have.
        $this->generate(['period' => '2027-01'])->assertUnprocessable()->assertJsonValidationErrors(['period']);
        $this->generate(['period' => '2026-07', 'section_id' => $this->sevenA->id])->assertUnprocessable()->assertJsonValidationErrors(['section_id']);
        $this->generate(['period' => '2026-07', 'class_id' => $this->seven->id])->assertUnprocessable()->assertJsonValidationErrors(['class_id']);
        $hostel = FeeType::create(['name' => 'Hostel', 'category' => 'hostel']);
        $this->generate(['period' => '2026-07', 'fee_type_ids' => [$hostel->id]])->assertUnprocessable()->assertJsonValidationErrors(['fee_type_ids']);
        $this->generate(['period' => '2026-07'], $this->userWithRole('Principal'))->assertForbidden();

        $this->assertSame(1, AuditLog::query()->where('action', 'fees.invoices_generated')->where('new_values->period', '2026-03')->where('new_values->created', 3)->count());

        $this->expectException(UniqueConstraintViolationException::class);
        $copy = $this->invoiceOf($this->sumi, '2026-03')->replicate(['live_period']);
        $copy->forceFill(['invoice_no' => 'INV-TEST'])->save();
    }

    public function test_an_unpaid_invoice_can_be_voided_which_frees_its_month(): void
    {
        $this->generate(['period' => '2026-03'])->assertAccepted();
        $invoice = $this->invoiceOf($this->rahim, '2026-03');

        $this->asUser($this->userWithRole('Teacher'))->postJson("/api/fee-invoices/{$invoice->id}/void", ['reason' => 'Wrong class'])->assertForbidden();
        $this->asUser($this->accountant)->postJson("/api/fee-invoices/{$invoice->id}/void", [])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->asUser($this->accountant)->postJson("/api/fee-invoices/{$invoice->id}/void", ['reason' => 'Billed twice'])->assertOk()
            ->assertJsonPath('data.status', 'void')->assertJsonPath('data.void_reason', 'Billed twice');
        $this->asUser($this->accountant)->postJson("/api/fee-invoices/{$invoice->id}/void", ['reason' => 'Again'])->assertConflict();
        $log = AuditLog::query()->where('action', 'fees.invoice_voided')->sole();
        $this->assertSame(['unpaid', 'void', 'fee_invoice'], [$log->old_values['status'], $log->new_values['status'], $log->auditable_type]);

        // Money received blocks voiding (refund first, Module 20).
        $karims = $this->invoiceOf($this->karim, '2026-03');
        $karims->forceFill(['paid' => '1000.00', 'due' => '5500.00', 'status' => 'partial'])->save();
        $this->asUser($this->accountant)->postJson("/api/fee-invoices/{$karims->id}/void", ['reason' => 'Mistake'])->assertConflict();

        // The month is free again for Rahim only; the void invoice stays on record.
        $this->generate(['period' => '2026-03'])->assertJsonPath('data.created', 1)->assertJsonPath('data.skipped', 2);
        $this->assertSame(2, FeeInvoice::query()->where('student_id', $this->rahim->id)->where('period', '2026-03')->count());
        // Admission was on the void invoice only, so it's billed on the new one.
        $this->assertSame('6500.00', $this->invoiceOf($this->rahim, '2026-03')->total);
    }

    public function test_lists_reports_and_each_familys_own_invoices(): void
    {
        $this->generate(['period' => '2026-03', 'due_date' => '2026-03-10'])->assertAccepted();
        $this->generate(['period' => '2026-04', 'due_date' => '2026-04-10'])->assertAccepted();
        $this->invoiceOf($this->karim, '2026-03')->forceFill(['paid' => '6500.00', 'due' => '0.00', 'status' => 'paid'])->save();

        $list = $this->asUser($this->accountant)->getJson('/api/fee-invoices?status=outstanding')->assertOk()->json();
        $this->assertSame(5, $list['meta']['total']);
        $this->assertSame(['billed' => '17500.00', 'collected' => '0.00', 'due' => '17500.00', 'invoices' => 5], $list['totals']);
        $this->asUser($this->accountant)->getJson('/api/fee-invoices?search=Sumi')->assertJsonCount(2, 'data');
        $no = $this->invoiceOf($this->rahim, '2026-04')->invoice_no;
        $this->asUser($this->accountant)->getJson("/api/fee-invoices?search={$no}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_no', $no);
        $this->assertSame('6500.00', $this->asUser($this->accountant)->getJson('/api/fee-invoices?period=2026-03')->json('totals.collected'));

        // Due report: most owed first; overdue once the due date has passed.
        $dues = $this->asUser($this->accountant)->getJson('/api/fees/reports/due')->assertOk()->json();
        $this->assertSame([['Rahim', '8000.00', 2], ['Sumi', '8000.00', 2], ['Karim', '1500.00', 1]], array_map(fn ($r) => [$r['student']['name'], $r['due'], $r['invoices']], $dues['data']));
        $this->assertSame(['students' => 3, 'invoices' => 5, 'due' => '17500.00'], $dues['totals']);
        $this->assertSame(['Class 6', 'A', '2026-03'], [$dues['data'][0]['class'], $dues['data'][0]['section'], $dues['data'][0]['oldest_period']]);
        $this->assertSame([], $this->asUser($this->accountant)->getJson('/api/fees/reports/due?overdue=1')->json('data'));
        $this->travelTo('2026-03-20 10:00');
        $this->assertSame(2, $this->asUser($this->accountant)->getJson('/api/fees/reports/due?overdue=1')->json('totals.students'));
        $this->asUser($this->userWithRole('Receptionist'))->getJson('/api/fees/reports/due')->assertForbidden();

        // A student sees only their own invoices; a parent their children's; neither reaches the staff endpoints.
        $student = User::factory()->create(['personable_type' => 'student', 'personable_id' => $this->rahim->id]);
        $student->assignRole('Student');
        $mine = $this->asUser($student)->getJson('/api/fees/me')->assertOk()->json('data');
        $this->assertSame(['student', 'Rahim', '8000.00', 2], [$mine['type'], $mine['students'][0]['student']['name'], $mine['students'][0]['due'], count($mine['students'][0]['invoices'])]);
        // Newest month first, with their lines: April tuition; March tuition + admission.
        $this->assertSame([['2026-04', 1], ['2026-03', 2]], array_map(fn ($i) => [$i['period'], count($i['items'])], $mine['students'][0]['invoices']));
        $this->asUser($student)->getJson('/api/fee-invoices')->assertForbidden();
        $this->asUser($student)->getJson('/api/fee-invoices/'.$this->invoiceOf($this->sumi, '2026-03')->id)->assertForbidden();

        $guardian = Guardian::create(['name' => 'Sumi\'s mother', 'phone' => '01800000001']);
        $guardian->students()->attach($this->sumi->id, ['relationship' => 'mother', 'is_primary' => true]);
        $parent = User::factory()->create(['personable_type' => 'guardian', 'personable_id' => $guardian->id]);
        $parent->assignRole('Parent');
        $this->assertSame(['Sumi'], array_column(array_column($this->asUser($parent)->getJson('/api/fees/me')->json('data.students'), 'student'), 'name'));

        // Dashboards.
        $widget = fn (User $u, string $key) => collect($this->asUser($u)->getJson('/api/dashboard')->json('data.widgets'))->firstWhere('key', $key);
        $this->assertSame(['ready', '24000.00', '6500.00', '17500.00', 2], array_values(array_intersect_key(
            ['status' => $widget($this->admin, 'pending_fees')['status']] + $widget($this->admin, 'pending_fees')['data'],
            array_flip(['status', 'billed', 'collected', 'due', 'overdue']),
        )));
        $myFees = $widget($student, 'my_fees')['data'];
        $this->assertSame(['8000.00', ['2026-03', '2026-04']], [$myFees['students'][0]['due'], array_column($myFees['students'][0]['unpaid'], 'period')]);
        $this->assertSame('8000.00', $widget($parent, 'child_fees')['data']['students'][0]['due']);

        $this->assertSame(0, FeeInvoiceRun::query()->where('status', '!=', 'completed')->count());

        // Even if a role edit gives Parents fee.view, the staff fee screens stay closed to them.
        Role::findByName('Parent', 'api')->givePermissionTo(['fee.view', 'fee.report']);
        $this->asUser($parent)->getJson('/api/fee-invoices')->assertForbidden();
        $this->asUser($parent)->getJson('/api/fee-invoices/'.$this->invoiceOf($this->rahim, '2026-03')->id)->assertForbidden();
        $this->asUser($parent)->getJson('/api/fees/reports/due')->assertForbidden();
        $this->asUser($parent)->getJson('/api/fees/me')->assertOk();
    }

    public function test_generation_runs_are_guarded_and_traceable(): void
    {
        // A second run for the same class and month waits for the first.
        $queue = Queue::getFacadeRoot();
        Queue::fake();
        $run = FeeInvoiceRun::findOrFail($this->generate(['period' => '2026-03'])->assertAccepted()->json('data.id'));
        $this->generate(['period' => '2026-03', 'section_id' => $this->sixA->id])->assertConflict();
        $this->generate(['period' => '2026-04'])->assertAccepted(); // another month is fine
        // A run stuck for over 15 minutes (worker down) no longer blocks.
        $this->travel(16)->minutes();
        $this->generate(['period' => '2026-03'])->assertAccepted();
        Queue::swap($queue);

        // The request is audited with who and from where.
        $requested = AuditLog::query()->where('action', 'fees.generation_requested')->where('auditable_id', $run->id)->sole();
        $this->assertSame([$this->accountant->id, '127.0.0.1', '2026-03'], [$requested->user_id, $requested->ip_address, $requested->new_values['period']]);

        // A run that stops partway keeps its counts, says so plainly, and is audited.
        (new GenerateInvoicesJob($run->id))->handle(app(GenerateInvoices::class));
        $run->refresh()->forceFill(['status' => 'running'])->save();
        (new GenerateInvoicesJob($run->id))->failed(new \RuntimeException('SQLSTATE[HY000] secret host details'));
        $run->refresh();
        $this->assertSame(['failed', 3, '19500.00'], [$run->status, $run->created, Money::format($run->total)]);
        $this->assertStringNotContainsString('SQLSTATE', $run->error);
        $this->assertSame(3, AuditLog::query()->where('action', 'fees.invoices_generation_failed')->sole()->new_values['created']);

        // Money must look like money; impossible statuses are refused.
        $structure = fn (string $amount) => $this->asUser($this->accountant)->postJson('/api/fee-structures', [
            'fee_type_id' => $this->types['Tuition']->id, 'class_id' => $this->seven->id, 'academic_year_id' => $this->y2026->id,
            'amount' => $amount, 'frequency' => 'monthly',
        ]);
        foreach (['+1500', '.5', '1500.', '1e3', '-10'] as $bad) {
            $structure($bad)->assertUnprocessable()->assertJsonValidationErrors(['amount']);
        }
        $this->expectException(\InvalidArgumentException::class);
        InvoiceStatus::for(150000, 150001);
    }
}
