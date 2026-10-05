import type { Paginated } from "@/types/api";

export type ExamType =
  "monthly" | "half_yearly" | "annual" | "model" | "test" | "custom";

/** Mirrors App\Enums\ExamType on the backend. */
export const EXAM_TYPES: { value: ExamType; label: string }[] = [
  { value: "monthly", label: "Monthly test" },
  { value: "half_yearly", label: "Half yearly" },
  { value: "annual", label: "Annual" },
  { value: "model", label: "Model test" },
  { value: "test", label: "Test exam" },
  { value: "custom", label: "Custom" },
];

export interface Exam {
  id: number;
  name: string;
  type: ExamType;
  academic_year?: { id: number; name: string };
  grade_scale?: { id: number; name: string };
  /** YYYY-MM-DD. */
  start_date: string;
  /** Null for a one-day exam. */
  end_date: string | null;
  papers_count?: number;
  classes_count?: number;
  archived: boolean;
  created_at?: string;
  updated_at?: string;
}

/** GET /exams/{id} also lists the classes taking it. */
export interface ExamDetail {
  data: Exam;
  classes: { id: number; name: string; papers: number }[];
}

export interface ExamInput {
  name: string;
  type: ExamType;
  /** Create only — the year is fixed afterwards. */
  academic_year_id?: number;
  grade_scale_id: number;
  start_date: string;
  end_date: string | null;
}

export interface ExamFilters {
  page?: number;
  academicYearId?: number | null;
  type?: ExamType | "";
  archived?: boolean;
}

/** The exam fields shown alongside a schedule. */
export interface ExamHeader {
  id: number;
  name: string;
  type: ExamType;
  start_date: string;
  end_date: string | null;
}

/** One paper: a class subject with its marks and when it's sat. */
export interface ExamPaper {
  id: number;
  class_subject_id: number;
  class?: { id: number; name: string };
  /** Null = every section of the class. */
  section?: { id: number; name: string } | null;
  subject?: { id: number; name: string; code: string | null };
  full_marks: number;
  pass_marks: number;
  cq_marks: number | null;
  mcq_marks: number | null;
  practical_marks: number | null;
  exam_date: string | null;
  start_time: string | null;
  end_time: string | null;
}

export interface ExamPaperInput {
  class_subject_id: number;
  section_id: number | null;
  full_marks: number;
  pass_marks: number;
  cq_marks: number | null;
  mcq_marks: number | null;
  practical_marks: number | null;
  exam_date: string | null;
  start_time: string | null;
  end_time: string | null;
}

/** GET|PUT /exams/{id}/classes/{classId}/subjects. */
export interface ClassPapers {
  exam: ExamHeader;
  class: { id: number; name: string };
  sections: { id: number; name: string }[];
  subjects: {
    class_subject_id: number;
    subject: {
      id: number;
      name: string;
      code: string | null;
      type: string | null;
    };
  }[];
  papers: ExamPaper[];
}

export interface ExamSchedule {
  exam: ExamHeader;
  papers: ExamPaper[];
}

/** GET /exams/me: one group per section the caller is tied to. */
export interface MyExams {
  academic_year: { id: number; name: string };
  type: "teacher" | "student" | "parent" | "none";
  groups: {
    student: { id: number; name: string } | null;
    section: { id: number; name: string; class: string } | null;
    exams: (ExamHeader & { papers: ExamPaper[] })[];
  }[];
}

export interface Grade {
  id?: number;
  name: string;
  grade_point: number;
  /** The band starts here (percentage of full marks)… */
  min_percent: number;
  /** …and runs up to the next band's start; null for the top band (to 100). */
  below_percent?: number | null;
}

export interface GradeScale {
  id: number;
  name: string;
  exams_count?: number;
  grades: Grade[];
  updated_at?: string;
}

export interface GradeScaleInput {
  name: string;
  grades: Omit<Grade, "below_percent">[];
}

/** The Bangladesh GPA 5.00 bands, offered when starting a new scale. */
export const STANDARD_GRADES: Omit<Grade, "id" | "below_percent">[] = [
  { name: "A+", grade_point: 5, min_percent: 80 },
  { name: "A", grade_point: 4, min_percent: 70 },
  { name: "A-", grade_point: 3.5, min_percent: 60 },
  { name: "B", grade_point: 3, min_percent: 50 },
  { name: "C", grade_point: 2, min_percent: 40 },
  { name: "D", grade_point: 1, min_percent: 33 },
  { name: "F", grade_point: 0, min_percent: 0 },
];

export type ExamPage = Paginated<Exam>;
