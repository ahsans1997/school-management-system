import { authedFetch } from "@/services/authService";
import type {
  MyResults,
  ReportCard,
  ResultPage,
  ResultStatistics,
  ResultStep,
  ResultsOverview,
  Tabulation,
} from "@/types/result";

/** Each class sitting the exam: marks progress and where its results are. */
export function getOverview(examId: number): Promise<ResultsOverview> {
  return authedFetch<ResultsOverview>(`/exams/${examId}/results/classes`);
}

/** Calculate (or recalculate) a class's draft results from submitted marks. */
export function calculate(
  examId: number,
  classId: number,
): Promise<ResultsOverview> {
  return authedFetch<ResultsOverview>(`/exams/${examId}/results/calculate`, {
    method: "POST",
    body: { class_id: classId },
  });
}

/** Move a class's results one step through the workflow (audited). */
export function step(
  examId: number,
  classId: number,
  action: ResultStep,
): Promise<ResultsOverview> {
  return authedFetch<ResultsOverview>(`/exams/${examId}/results/${action}`, {
    method: "POST",
    body: { class_id: classId },
  });
}

function query(classId: number, sectionId?: number | null, page?: number) {
  const params = new URLSearchParams({ class_id: String(classId) });
  if (sectionId) params.set("section_id", String(sectionId));
  if (page) params.set("page", String(page));
  return params.toString();
}

/** The merit list: class order, or section order with a section. */
export function listResults(
  examId: number,
  classId: number,
  { sectionId = null, page = 1 }: { sectionId?: number | null; page?: number },
): Promise<ResultPage> {
  return authedFetch<ResultPage>(
    `/exams/${examId}/results?${query(classId, sectionId, page)}`,
  );
}

export function getTabulation(
  examId: number,
  classId: number,
  sectionId?: number | null,
): Promise<Tabulation> {
  return authedFetch<{ data: Tabulation }>(
    `/exams/${examId}/results/tabulation?${query(classId, sectionId)}`,
  ).then((r) => r.data);
}

export function getStatistics(
  examId: number,
  classId: number,
  sectionId?: number | null,
): Promise<ResultStatistics> {
  return authedFetch<{ data: ResultStatistics }>(
    `/exams/${examId}/results/statistics?${query(classId, sectionId)}`,
  ).then((r) => r.data);
}

export function getReportCard(id: number): Promise<ReportCard> {
  return authedFetch<{ data: ReportCard }>(`/results/${id}/report-card`).then(
    (r) => r.data,
  );
}

/** A student's or parent's own published results. */
export function getMyResults(): Promise<MyResults> {
  return authedFetch<{ data: MyResults }>("/results/me").then((r) => r.data);
}
