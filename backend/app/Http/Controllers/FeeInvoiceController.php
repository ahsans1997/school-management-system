<?php

namespace App\Http\Controllers;

use App\Actions\Fee\GenerateInvoices;
use App\Actions\Fee\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Fee\GenerateInvoicesRequest;
use App\Http\Requests\Fee\VoidInvoiceRequest;
use App\Http\Resources\FeeInvoiceResource;
use App\Http\Resources\FeeInvoiceRunResource;
use App\Models\AcademicYear;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceRun;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Student;
use App\Services\Fee\FeeService;
use App\Support\PerPage;
use App\Support\SchoolLetterhead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Fee invoices (Module 19). Staff with fee.view see every invoice
 * (FeeInvoicePolicy also keeps Parent/Student logins out, whatever their
 * role is given); students and parents see only their own, via /fees/me. Generating runs in the
 * background (202 + a run to poll). Invoices are voided, never deleted.
 */
class FeeInvoiceController extends Controller
{
    public function __construct(private readonly FeeService $fees) {}

    /**
     * Paginated, newest month first, with totals for the same filters.
     * ?academic_year_id, ?class_id, ?section_id, ?period, ?status (or
     * "outstanding"), ?search (name, student ID, invoice no.).
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', FeeInvoice::class);
        $filters = request()->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in([...array_column(InvoiceStatus::cases(), 'value'), 'outstanding'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $query = $this->fees->invoices($filters);
        $page = (clone $query)->paginate(PerPage::from(request()));

        return FeeInvoiceResource::collection($page)->additional([
            'totals' => $this->fees->totals($query),
            'currency' => SchoolSetting::current()->currency,
        ])->response();
    }

    /** One invoice with its lines, for screen and print. */
    public function show(FeeInvoice $feeInvoice): JsonResponse
    {
        Gate::authorize('view', $feeInvoice);

        return (new FeeInvoiceResource($feeInvoice->load(['items', 'student', 'enrollment.section', 'schoolClass', 'academicYear', 'issuedBy', 'voidedBy'])))
            ->additional(['school' => SchoolLetterhead::toArray(), 'currency' => SchoolSetting::current()->currency])
            ->response();
    }

    /** Queue a month's invoices for a class or section: 202 with the run. */
    public function generate(GenerateInvoicesRequest $request, GenerateInvoices $generate): JsonResponse
    {
        Gate::authorize('create', FeeInvoice::class);
        $run = $generate->queue($request->user(), $request->validated());

        return (new FeeInvoiceRunResource($run->load(['academicYear', 'schoolClass', 'section', 'requestedBy'])))
            ->response()->setStatusCode(202);
    }

    /** Recent generation runs, newest first. */
    public function runs(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FeeInvoice::class);

        return FeeInvoiceRunResource::collection(FeeInvoiceRun::query()
            ->with(['academicYear', 'schoolClass', 'section', 'requestedBy'])
            ->latest('id')
            ->paginate(PerPage::from(request())));
    }

    public function run(FeeInvoiceRun $feeInvoiceRun): FeeInvoiceRunResource
    {
        Gate::authorize('viewAny', FeeInvoice::class);

        return new FeeInvoiceRunResource($feeInvoiceRun->load(['academicYear', 'schoolClass', 'section', 'requestedBy']));
    }

    /** Cancel an invoice nothing has been paid against. */
    public function void(VoidInvoiceRequest $request, FeeInvoice $feeInvoice, VoidInvoice $void): JsonResponse
    {
        Gate::authorize('cancel', $feeInvoice);
        $void->execute($request->user(), $feeInvoice, $request->string('reason')->toString());

        return $this->show($feeInvoice->refresh());
    }

    /**
     * Pickers for the fee screens: years, classes with their sections, fee
     * types. Under fee.view so finance staff needn't hold class/year rights.
     */
    public function options(): JsonResponse
    {
        Gate::authorize('viewAny', FeeInvoice::class);

        return response()->json(['data' => [
            'academic_years' => AcademicYear::query()->orderByDesc('start_date')->get()
                ->map(fn (AcademicYear $y) => ['id' => $y->id, 'name' => $y->name, 'is_current' => (bool) $y->is_current])->values(),
            'classes' => SchoolClass::query()->with(['sections' => fn ($q) => $q->orderBy('name')])->orderBy('numeric_level')->get()
                ->map(fn (SchoolClass $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'sections' => $c->sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->name])->values(),
                ])->values(),
            'fee_types' => FeeType::query()->orderBy('name')->get()
                ->map(fn (FeeType $t) => ['id' => $t->id, 'name' => $t->name, 'category' => $t->category->value, 'is_active' => $t->is_active])->values(),
            'currency' => SchoolSetting::current()->currency,
        ]]);
    }

    /** The caller's own invoices: a student's, or each of a parent's children's. */
    public function me(): JsonResponse
    {
        $mine = $this->fees->mine(request()->user());

        return response()->json(['data' => [
            'type' => $mine['type'],
            'currency' => SchoolSetting::current()->currency,
            'students' => array_map(fn (array $s) => [
                'student' => ['id' => $s['student']->id, 'name' => $s['student']->name],
                'due' => $s['due'],
                'invoices' => FeeInvoiceResource::collection($s['invoices'])->resolve(),
            ], $mine['students']),
        ]]);
    }

    /** Outstanding dues by student (fee.report). ?academic_year_id, ?class_id, ?section_id, ?overdue. */
    public function dues(): JsonResponse
    {
        Gate::authorize('report', FeeInvoice::class);
        $filters = request()->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'overdue' => ['nullable', 'boolean'],
        ]);
        $filters['overdue'] = request()->boolean('overdue');
        $page = $this->fees->dueReport($filters, PerPage::from(request()));

        return response()->json([
            'data' => $this->fees->dueRows($page->items()),
            'totals' => $this->fees->dueTotals($filters),
            'currency' => SchoolSetting::current()->currency,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
