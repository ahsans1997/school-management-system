import type { Paginated } from "@/types/api";
import type { ExamHeader, ExamPaper } from "@/types/exam";

/** The parts a paper can be split into (mirrors ExamSubject::PARTS). */
export type MarkPart = "cq" | "mcq" | "practical";

export const MARK_PARTS: {
  value: MarkPart;
  label: string;
  /** The paper's cap for this part. */
  cap: "cq_marks" | "mcq_marks" | "practical_marks";
}[] = [
  { value: "cq", label: "CQ", cap: "cq_marks" },
  { value: "mcq", label: "MCQ", cap: "mcq_marks" },
  { value: "practical", label: "Practical", cap: "practical_marks" },
];

/** A sheet is "submitted" once locked for results; mirrors App\Enums\MarkStatus. */
export type MarkSheetStatus = "not_started" | "draft" | "submitted";

export const SHEET_STATUSES: { value: MarkSheetStatus; label: string }[] = [
  { value: "not_started", label: "Not started" },
  { value: "draft", label: "Draft" },
  { value: "submitted", label: "Submitted" },
];

/** A student's marks on one paper. Internal only — never published raw. */
export interface Mark {
  id: number;
  exam_subject_id: number;
  student_id: number;
  cq: number | null;
  mcq: number | null;
  practical: number | null;
  /** Computed by the server; null when absent. */
  total: number | null;
  absent: boolean;
  status: "draft" | "submitted";
  updated_at: string | null;
}

/** GET /exams/{id}/marks/sheet: one paper as sat by one section. */
export interface MarkSheet {
  exam: ExamHeader;
  paper: ExamPaper;
  section: { id: number; name: string; class: string };
  /** The paper's split, in order; empty = marked as one figure. */
  parts: MarkPart[];
  /** YYYY-MM-DD: marks can be entered from the day the paper is sat. */
  opens_on: string;
  open: boolean;
  status: MarkSheetStatus;
  submitted: { at: string | null; by: string | null } | null;
  can_edit: boolean;
  can_reopen: boolean;
  summary: { students: number; entered: number; absent: number };
  students: {
    student_id: number;
    name: string;
    /** Null for a student no longer on the roll who already has marks. */
    roll: number | null;
    mark: Mark | null;
  }[];
}

/** One row of GET /exams/{id}/marks. */
export interface MarkSheetSummary {
  exam_subject_id: number;
  class: { id: number; name: string };
  section: { id: number; name: string };
  subject: { id: number; name: string; code: string | null };
  full_marks: number;
  exam_date: string | null;
  opens_on: string;
  open: boolean;
  students: number;
  entered: number;
  status: MarkSheetStatus;
}

export interface MarkSheetPage extends Paginated<MarkSheetSummary> {
  exam: ExamHeader;
  /** Classes the caller has sheets in (for the filter). */
  classes: { id: number; name: string }[];
}

/** One row as saved; `marks` is for papers without a split. */
export interface MarkRecordInput {
  student_id: number;
  absent: boolean;
  cq: number | null;
  mcq: number | null;
  practical: number | null;
  marks: number | null;
}
