import type { Paginated } from "@/types/api";
import type { ExamHeader } from "@/types/exam";
import type { Letterhead } from "@/types/studentTools";

/** Mirrors App\Enums\ResultStatus: a class's results move together. */
export type ResultStatus = "draft" | "review" | "approved" | "published";

export const RESULT_STATUSES: { value: ResultStatus; label: string }[] = [
  { value: "draft", label: "Draft" },
  { value: "review", label: "In review" },
  { value: "approved", label: "Approved" },
  { value: "published", label: "Published" },
];

/** Workflow steps after calculation (POST /exams/{id}/results/{step}). */
export type ResultStep =
  "review" | "approve" | "publish" | "return" | "unpublish";

/** GET /exams/{id}/results/classes: one row per class sitting the exam. */
export interface ResultClassOverview {
  class: { id: number; name: string };
  /** Mark sheets (paper × section) the results depend on. */
  sheets: { total: number; submitted: number };
  /** A few of the sheets not submitted yet, e.g. "Class 6 A · Mathematics". */
  pending: string[];
  /** Null until calculated. */
  status: ResultStatus | null;
  students: number;
  passed: number;
  calculated_at: string | null;
  published_at: string | null;
  /** Calculated before the grade scale changed: recalculate before moving on. */
  stale: boolean;
}

export interface ResultsOverview {
  data: ResultClassOverview[];
  exam: ExamHeader;
}

export interface ResultRow {
  id: number;
  student: { id: number; name: string; student_id: string };
  roll: number;
  class: { id: number; name: string };
  section: { id: number; name: string };
  total_marks: number;
  full_marks: number;
  percentage: number;
  gpa: number;
  grade: string;
  passed: boolean;
  failed_subjects: number;
  /** Merit within the section / the class; null when a subject was failed. */
  position: number | null;
  class_position: number | null;
  status: ResultStatus;
}

export type ResultPage = Paginated<ResultRow>;

/** The context the tabulation and statistics endpoints return. */
export interface ResultHeader {
  exam: ExamHeader;
  class: { id: number; name: string };
  section: { id: number; name: string } | null;
  sections: { id: number; name: string }[];
  status: ResultStatus | null;
}

export interface SubjectCell {
  obtained: number | null;
  absent: boolean;
  grade: string;
  grade_point: number;
  passed: boolean;
}

export interface Tabulation extends ResultHeader {
  subjects: {
    exam_subject_id: number;
    subject: string;
    code: string | null;
    full_marks: number;
  }[];
  /** `subjects` is keyed by exam_subject_id; a missing key = not sat. */
  rows: (ResultRow & { subjects: Record<string, SubjectCell> })[];
}

export interface ResultStatistics extends ResultHeader {
  students: number;
  passed: number;
  failed: number;
  pass_rate: number | null;
  average_gpa: number | null;
  highest_total: number | null;
  grades: { grade: string; students: number }[];
  by_section: {
    section: { id: number; name: string };
    students: number;
    passed: number;
    pass_rate: number;
    average_gpa: number;
  }[];
  subjects: {
    exam_subject_id: number;
    subject: string;
    full_marks: number;
    students: number;
    absent: number;
    passed: number;
    pass_rate: number;
    highest: number | null;
    average: number | null;
  }[];
}

export interface ReportCardSubject extends SubjectCell {
  exam_subject_id: number;
  subject: string;
  code: string | null;
  full_marks: number;
  pass_marks: number;
  cq: number | null;
  mcq: number | null;
  practical: number | null;
  percentage: number;
}

/** GET /results/{id}/report-card (and each entry of /results/me). */
export interface ReportCard extends Omit<ResultRow, "student"> {
  school: Letterhead;
  exam: ExamHeader & { academic_year: string };
  student: {
    id: number;
    name: string;
    student_id: string;
    admission_no: string;
    father_name: string | null;
    mother_name: string | null;
  };
  subjects: ReportCardSubject[];
  section_students: number;
  class_students: number;
  published_at: string | null;
}

/** GET /results/me: published results only. */
export interface MyResults {
  type: "student" | "parent" | "none";
  students: {
    student: { id: number; name: string };
    results: ReportCard[];
  }[];
}
