import type { Paginated } from "@/types/api";
import type { Letterhead } from "@/types/studentTools";

/** Money from the API is a "1500.00" string — never do maths on floats. */
export type Money = string;

export type FeeCategory =
  | "admission"
  | "tuition"
  | "exam"
  | "transport"
  | "library"
  | "hostel"
  | "other";

/** Mirrors App\Enums\FeeCategory. */
export const FEE_CATEGORIES: { value: FeeCategory; label: string }[] = [
  { value: "tuition", label: "Tuition" },
  { value: "admission", label: "Admission" },
  { value: "exam", label: "Exam" },
  { value: "transport", label: "Transport" },
  { value: "library", label: "Library" },
  { value: "hostel", label: "Hostel" },
  { value: "other", label: "Other" },
];

export type FeeFrequency = "monthly" | "one_time" | "exam";

/** Mirrors App\Enums\FeeFrequency. */
export const FEE_FREQUENCIES: {
  value: FeeFrequency;
  label: string;
  hint: string;
}[] = [
  {
    value: "monthly",
    label: "Every month",
    hint: "On every monthly invoice",
  },
  {
    value: "one_time",
    label: "Once a year",
    hint: "On the first invoice of the year that includes it",
  },
  {
    value: "exam",
    label: "When chosen",
    hint: "Only on invoices you choose it for (exam fees)",
  },
];

export type InvoiceStatus = "unpaid" | "partial" | "paid" | "void";

/** Mirrors App\Enums\InvoiceStatus. */
export const INVOICE_STATUSES: { value: InvoiceStatus; label: string }[] = [
  { value: "unpaid", label: "Unpaid" },
  { value: "partial", label: "Partly paid" },
  { value: "paid", label: "Paid" },
  { value: "void", label: "Void" },
];

export interface FeeType {
  id: number;
  name: string;
  code: string | null;
  category: FeeCategory;
  is_active: boolean;
  structures_count?: number;
}

export interface FeeTypeInput {
  name: string;
  code: string | null;
  category: FeeCategory;
  is_active: boolean;
}

export interface FeeStructure {
  id: number;
  fee_type?: {
    id: number;
    name: string;
    category: FeeCategory;
    is_active: boolean;
  };
  class?: { id: number; name: string };
  academic_year?: { id: number; name: string };
  amount: Money;
  frequency: FeeFrequency;
  is_active: boolean;
  updated_at: string | null;
}

/** Type, class and year are fixed once created; send them on create only. */
export interface FeeStructureInput {
  fee_type_id?: number;
  class_id?: number;
  academic_year_id?: number;
  amount: string;
  frequency: FeeFrequency;
  is_active: boolean;
}

export interface FeeInvoice {
  id: number;
  invoice_no: string;
  student?: { id: number; name: string; student_id: string };
  class?: { id: number; name: string };
  section?: { id: number; name: string } | null;
  roll?: number;
  academic_year?: { id: number; name: string };
  /** YYYY-MM */
  period: string;
  due_date: string;
  total: Money;
  paid: Money;
  due: Money;
  status: InvoiceStatus;
  issued_at: string;
  issued_by?: string | null;
  voided_at: string | null;
  voided_by?: string | null;
  void_reason: string | null;
  items?: {
    id: number;
    fee_type_id: number;
    description: string;
    amount: Money;
  }[];
}

export interface FeeTotals {
  billed: Money;
  collected: Money;
  due: Money;
  invoices: number;
}

export interface InvoicePage extends Paginated<FeeInvoice> {
  totals: FeeTotals;
  currency: string;
}

export interface InvoiceFilters {
  page?: number;
  academicYearId?: number | null;
  classId?: number | null;
  sectionId?: number | null;
  period?: string;
  status?: InvoiceStatus | "outstanding" | "";
  search?: string;
}

/** GET /fee-invoices/{id}: the invoice plus what's printed on it. */
export interface InvoiceDetail {
  data: FeeInvoice;
  school: Letterhead;
  currency: string;
}

export type RunStatus = "queued" | "running" | "completed" | "failed";

export interface InvoiceRun {
  id: number;
  academic_year?: { id: number; name: string };
  class?: { id: number; name: string };
  section?: { id: number; name: string } | null;
  period: string;
  due_date: string;
  fee_type_ids: number[] | null;
  status: RunStatus;
  created: number;
  skipped: number;
  total: Money;
  error: string | null;
  requested_by?: string | null;
  created_at: string | null;
  started_at: string | null;
  finished_at: string | null;
}

export interface GenerateInput {
  academic_year_id: number | null;
  class_id: number;
  section_id: number | null;
  period: string;
  due_date: string | null;
  /** Null = the defaults (monthly fees + one-time fees not yet billed). */
  fee_type_ids: number[] | null;
}

export interface DueRow {
  student: { id: number; name: string | null; student_id: string | null };
  class: string | null;
  section: string | null;
  roll: number | null;
  invoices: number;
  due: Money;
  oldest_period: string;
  earliest_due: string;
}

export interface DueReport extends Paginated<DueRow> {
  totals: { students: number; invoices: number; due: Money };
  currency: string;
}

/** GET /fees/options: pickers for the fee screens (under fee.view). */
export interface FeeOptions {
  academic_years: { id: number; name: string; is_current: boolean }[];
  classes: {
    id: number;
    name: string;
    sections: { id: number; name: string }[];
  }[];
  fee_types: {
    id: number;
    name: string;
    category: FeeCategory;
    is_active: boolean;
  }[];
  currency: string;
}

/** GET /fees/me: a student's own invoices, or each child's. */
export interface MyFees {
  type: "student" | "parent" | "none";
  currency: string;
  students: {
    student: { id: number; name: string };
    due: Money;
    invoices: FeeInvoice[];
  }[];
}
