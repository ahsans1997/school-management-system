import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type {
  DueReport,
  FeeOptions,
  FeeStructure,
  FeeStructureInput,
  FeeType,
  FeeTypeInput,
  GenerateInput,
  InvoiceDetail,
  InvoiceFilters,
  InvoicePage,
  InvoiceRun,
  MyFees,
} from "@/types/fee";

export function listFeeTypes(
  page = 1,
  perPage = 100,
): Promise<Paginated<FeeType>> {
  return authedFetch<Paginated<FeeType>>(
    `/fee-types?page=${page}&per_page=${perPage}`,
  );
}

export function createFeeType(input: FeeTypeInput): Promise<FeeType> {
  return authedFetch<{ data: FeeType }>("/fee-types", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateFeeType(
  id: number,
  input: FeeTypeInput,
): Promise<FeeType> {
  return authedFetch<{ data: FeeType }>(`/fee-types/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

export function deleteFeeType(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/fee-types/${id}`, { method: "DELETE" });
}

/** A class's fees for a year. */
export function listStructures(
  academicYearId: number,
  classId: number,
): Promise<FeeStructure[]> {
  return authedFetch<Paginated<FeeStructure>>(
    `/fee-structures?academic_year_id=${academicYearId}&class_id=${classId}&per_page=100`,
  ).then((r) => r.data);
}

export function createStructure(
  input: FeeStructureInput,
): Promise<FeeStructure> {
  return authedFetch<{ data: FeeStructure }>("/fee-structures", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateStructure(
  id: number,
  input: FeeStructureInput,
): Promise<FeeStructure> {
  return authedFetch<{ data: FeeStructure }>(`/fee-structures/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

export function deleteStructure(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/fee-structures/${id}`, { method: "DELETE" });
}

export function listInvoices(
  filters: InvoiceFilters = {},
): Promise<InvoicePage> {
  const params = new URLSearchParams({ page: String(filters.page ?? 1) });
  if (filters.academicYearId)
    params.set("academic_year_id", String(filters.academicYearId));
  if (filters.classId) params.set("class_id", String(filters.classId));
  if (filters.sectionId) params.set("section_id", String(filters.sectionId));
  if (filters.period) params.set("period", filters.period);
  if (filters.status) params.set("status", filters.status);
  if (filters.search) params.set("search", filters.search);
  return authedFetch<InvoicePage>(`/fee-invoices?${params}`);
}

export function getInvoice(id: number): Promise<InvoiceDetail> {
  return authedFetch<InvoiceDetail>(`/fee-invoices/${id}`);
}

/** Cancel an invoice nothing has been paid against. */
export function voidInvoice(
  id: number,
  reason: string,
): Promise<InvoiceDetail> {
  return authedFetch<InvoiceDetail>(`/fee-invoices/${id}/void`, {
    method: "POST",
    body: { reason },
  });
}

/** Queue a month's invoices; poll the returned run until it finishes. */
export function generateInvoices(input: GenerateInput): Promise<InvoiceRun> {
  return authedFetch<{ data: InvoiceRun }>("/fee-invoices/generate", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function getRun(id: number): Promise<InvoiceRun> {
  return authedFetch<{ data: InvoiceRun }>(`/fee-invoices/runs/${id}`).then(
    (r) => r.data,
  );
}

export function listRuns(): Promise<InvoiceRun[]> {
  return authedFetch<Paginated<InvoiceRun>>(
    "/fee-invoices/runs?per_page=10",
  ).then((r) => r.data);
}

export function getDueReport(
  filters: {
    page?: number;
    academicYearId?: number | null;
    classId?: number | null;
    overdue?: boolean;
  } = {},
): Promise<DueReport> {
  const params = new URLSearchParams({ page: String(filters.page ?? 1) });
  if (filters.academicYearId)
    params.set("academic_year_id", String(filters.academicYearId));
  if (filters.classId) params.set("class_id", String(filters.classId));
  if (filters.overdue) params.set("overdue", "1");
  return authedFetch<DueReport>(`/fees/reports/due?${params}`);
}

/** Years, classes with sections, and fee types for the pickers. */
export function getOptions(): Promise<FeeOptions> {
  return authedFetch<{ data: FeeOptions }>("/fees/options").then((r) => r.data);
}

/** A student's or parent's own invoices. */
export function getMyFees(): Promise<MyFees> {
  return authedFetch<{ data: MyFees }>("/fees/me").then((r) => r.data);
}
