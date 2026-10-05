import { authedFetch } from "@/services/authService";
import type {
  ClassPapers,
  Exam,
  ExamDetail,
  ExamFilters,
  ExamInput,
  ExamPage,
  ExamPaperInput,
  ExamSchedule,
  GradeScale,
  GradeScaleInput,
  MyExams,
} from "@/types/exam";

/** Newest first; filter by year, type, or archived. */
export function listExams({
  page = 1,
  academicYearId = null,
  type = "",
  archived = false,
}: ExamFilters = {}): Promise<ExamPage> {
  const params = new URLSearchParams({ page: String(page) });
  if (academicYearId) params.set("academic_year_id", String(academicYearId));
  if (type) params.set("type", type);
  if (archived) params.set("archived", "1");
  return authedFetch<ExamPage>(`/exams?${params}`);
}

export function getExam(id: number): Promise<ExamDetail> {
  return authedFetch<ExamDetail>(`/exams/${id}`);
}

export function createExam(input: ExamInput): Promise<Exam> {
  return authedFetch<{ data: Exam }>("/exams", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateExam(id: number, input: ExamInput): Promise<Exam> {
  return authedFetch<{ data: Exam }>(`/exams/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

/** Archive (soft delete); marks and results keep their exam. */
export function archiveExam(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/exams/${id}`, { method: "DELETE" });
}

export function restoreExam(id: number): Promise<Exam> {
  return authedFetch<{ data: Exam }>(`/exams/${id}/restore`, {
    method: "POST",
  }).then((r) => r.data);
}

/** Every paper, or one class's or section's (its class-wide papers + its own). */
export function getSchedule(
  examId: number,
  filter: { classId?: number; sectionId?: number } = {},
): Promise<ExamSchedule> {
  const params = new URLSearchParams();
  if (filter.sectionId) params.set("section_id", String(filter.sectionId));
  else if (filter.classId) params.set("class_id", String(filter.classId));
  const query = params.toString() ? `?${params}` : "";
  return authedFetch<{ data: ExamSchedule }>(
    `/exams/${examId}/schedule${query}`,
  ).then((r) => r.data);
}

export function getClassPapers(
  examId: number,
  classId: number,
): Promise<ClassPapers> {
  return authedFetch<{ data: ClassPapers }>(
    `/exams/${examId}/classes/${classId}/subjects`,
  ).then((r) => r.data);
}

/**
 * Replace one class's papers. Row errors come back keyed
 * `subjects.N.field` — see ApiRequestError.
 */
export function saveClassPapers(
  examId: number,
  classId: number,
  papers: ExamPaperInput[],
): Promise<ClassPapers> {
  return authedFetch<{ data: ClassPapers }>(
    `/exams/${examId}/classes/${classId}/subjects`,
    { method: "PUT", body: { subjects: papers.map((p) => ({ ...p })) } },
  ).then((r) => r.data);
}

/** The caller's upcoming exams: a teacher's sections, a student's, or each child's. */
export function getMyExams(): Promise<MyExams> {
  return authedFetch<{ data: MyExams }>("/exams/me").then((r) => r.data);
}

export function listGradeScales(): Promise<GradeScale[]> {
  return authedFetch<{ data: GradeScale[] }>("/grade-scales").then(
    (r) => r.data,
  );
}

export function createGradeScale(input: GradeScaleInput): Promise<GradeScale> {
  return authedFetch<{ data: GradeScale }>("/grade-scales", {
    method: "POST",
    body: { name: input.name, grades: input.grades.map((g) => ({ ...g })) },
  }).then((r) => r.data);
}

/** Send a band's id to keep it (marks may later point at it). */
export function updateGradeScale(
  id: number,
  input: GradeScaleInput,
): Promise<GradeScale> {
  return authedFetch<{ data: GradeScale }>(`/grade-scales/${id}`, {
    method: "PUT",
    body: { name: input.name, grades: input.grades.map((g) => ({ ...g })) },
  }).then((r) => r.data);
}

export function deleteGradeScale(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/grade-scales/${id}`, { method: "DELETE" });
}
