<?php

namespace App\Services\Fee;

use App\Enums\DashboardAudience;
use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use App\Support\SchoolClock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reading fees: invoice lists, the outstanding-dues report, totals for the
 * dashboard, and a student's or parent's own invoices. Sums are taken in
 * SQL and normalised through Money, never added as floats in PHP.
 */
class FeeService
{
    /**
     * Invoices for the list screen, newest month first.
     *
     * @param  array<string, mixed>  $filters  academic_year_id, class_id, section_id, period, status, search
     * @return Builder<FeeInvoice>
     */
    public function invoices(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $like = '%'.addcslashes($search, '%_\\').'%'; // % and _ typed by the user are literal

        return FeeInvoice::query()
            ->with(['student', 'enrollment.section', 'schoolClass'])
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))
            ->when($filters['section_id'] ?? null, fn ($q, $id) => $q->whereHas('enrollment', fn ($e) => $e->where('section_id', $id)))
            ->when($filters['period'] ?? null, fn ($q, $period) => $q->where('period', $period))
            ->when($filters['status'] ?? null, fn ($q, $status) => $status === 'outstanding'
                ? $q->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                : $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('invoice_no', 'like', $like)
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', $like)->orWhere('student_id', 'like', $like))))
            ->orderByDesc('period')
            ->orderBy('class_id')
            ->orderBy('id');
    }

    /**
     * Billed / collected / outstanding for the live invoices of a query.
     *
     * @param  Builder<FeeInvoice>  $query
     * @return array{billed: string, collected: string, due: string, invoices: int}
     */
    public function totals(Builder $query): array
    {
        $row = (clone $query)->live()->reorder()->toBase()
            ->selectRaw('count(*) as invoices, coalesce(sum(total), 0) as billed, coalesce(sum(paid), 0) as collected, coalesce(sum(due), 0) as due')
            ->first();

        return [
            'billed' => Money::format($row->billed ?? 0),
            'collected' => Money::format($row->collected ?? 0),
            'due' => Money::format($row->due ?? 0),
            'invoices' => (int) ($row->invoices ?? 0),
        ];
    }

    /**
     * Students with money outstanding, most owed first: how many invoices,
     * how much, the oldest unpaid month and the earliest due date.
     *
     * @param  array<string, mixed>  $filters  academic_year_id, class_id, section_id, overdue
     * @return LengthAwarePaginator<int, object>
     */
    public function dueReport(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->outstanding($filters)
            ->toBase()
            ->selectRaw('student_id, count(*) as invoices, sum(due) as due, min(period) as oldest_period, min(due_date) as earliest_due')
            ->groupBy('student_id')
            ->orderByRaw('sum(due) desc')
            ->orderBy('student_id')
            ->paginate($perPage);
    }

    /**
     * The due report's rows with each student's current class and section.
     *
     * @param  array<int, object>  $rows  from dueReport()
     * @return list<array<string, mixed>>
     */
    public function dueRows(array $rows): array
    {
        $students = Student::withTrashed()
            ->with(['enrollments' => fn ($q) => $q->with(['schoolClass', 'section'])->latest('id')])
            ->findMany(array_map(fn (object $row) => $row->student_id, $rows))
            ->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $student = $students->get($row->student_id);
            $enrollment = $student?->enrollments->first();
            $out[] = [
                'student' => ['id' => (int) $row->student_id, 'name' => $student?->name, 'student_id' => $student?->student_id],
                'class' => $enrollment?->schoolClass->name,
                'section' => $enrollment?->section->name,
                'roll' => $enrollment?->roll,
                'invoices' => (int) $row->invoices,
                'due' => Money::format($row->due),
                'oldest_period' => $row->oldest_period,
                'earliest_due' => substr((string) $row->earliest_due, 0, 10),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{students: int, invoices: int, due: string}
     */
    public function dueTotals(array $filters): array
    {
        $totals = $this->outstanding($filters)->toBase()
            ->selectRaw('count(*) as invoices, count(distinct student_id) as students, coalesce(sum(due), 0) as due')->first();

        return [
            'students' => (int) ($totals->students ?? 0),
            'invoices' => (int) ($totals->invoices ?? 0),
            'due' => Money::format($totals->due ?? 0),
        ];
    }

    /**
     * Unpaid or part-paid invoices matching the report filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FeeInvoice>
     */
    public function outstanding(array $filters): Builder
    {
        return FeeInvoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->where('due', '>', 0)
            ->when($filters['academic_year_id'] ?? null, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))
            ->when($filters['section_id'] ?? null, fn ($q, $id) => $q->whereHas('enrollment', fn ($e) => $e->where('section_id', $id)))
            ->when(! empty($filters['overdue']), fn ($q) => $q->whereDate('due_date', '<', SchoolClock::date()));
    }

    /**
     * The school-wide picture for the current year (admin dashboard).
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $year = AcademicYear::current();
        if ($year === null) {
            return ['academic_year' => null, 'billed' => '0.00', 'collected' => '0.00', 'due' => '0.00', 'invoices' => 0, 'overdue' => 0, 'overdue_amount' => '0.00'];
        }
        $overdue = $this->outstanding(['academic_year_id' => $year->id, 'overdue' => true])->toBase()
            ->selectRaw('count(*) as invoices, coalesce(sum(due), 0) as due')->first();

        return [
            'academic_year' => $year->name,
            ...$this->totals(FeeInvoice::query()->where('academic_year_id', $year->id)),
            'overdue' => (int) ($overdue->invoices ?? 0),
            'overdue_amount' => Money::format($overdue->due ?? 0),
        ];
    }

    /**
     * The caller's own invoices — a student's, or each of a parent's
     * children's — live ones, newest month first, with what's still due.
     *
     * @return array{type: string, students: list<array{student: Student, due: string, invoices: Collection<int, FeeInvoice>}>}
     */
    public function mine(User $user): array
    {
        [$type, $students] = match (DashboardAudience::forUser($user)) {
            DashboardAudience::Student => ['student', collect([$this->personOf($user, Student::class)])->filter()],
            DashboardAudience::Parent => ['parent', $this->personOf($user, Guardian::class)?->students()->orderBy('name')->get() ?? collect()],
            default => ['none', collect()],
        };

        return [
            'type' => $type,
            'students' => $students->map(function (Student $student) {
                $invoices = FeeInvoice::query()->live()
                    ->with(['items', 'schoolClass', 'academicYear'])
                    ->where('student_id', $student->id)
                    ->orderByDesc('period')->orderByDesc('id')
                    ->get();

                return [
                    'student' => $student,
                    'due' => Money::decimal($invoices->sum(fn (FeeInvoice $i) => Money::cents($i->due))),
                    'invoices' => $invoices,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function personOf(User $user, string $model)
    {
        return $user->personable_type === (new $model)->getMorphClass() ? $model::find($user->personable_id) : null;
    }
}
