import { authedFetch } from "@/services/authService";
import type {
  Mark,
  MarkRecordInput,
  MarkSheet,
  MarkSheetPage,
} from "@/types/marks";

/** The caller's sheets for an exam (teachers: their own subjects + sections). */
export function listSheets(
  examId: number,
  { page = 1, classId = null }: { page?: number; classId?: number | null } = {},
): Promise<MarkSheetPage> {
  const params = new URLSearchParams({ page: String(page) });
  if (classId) params.set("class_id", String(classId));
  return authedFetch<MarkSheetPage>(`/exams/${examId}/marks?${params}`);
}

const sheetBody = (paperId: number, sectionId: number) => ({
  exam_subject_id: paperId,
  section_id: sectionId,
});

export function getSheet(
  examId: number,
  paperId: number,
  sectionId: number,
): Promise<MarkSheet> {
  return authedFetch<{ data: MarkSheet }>(
    `/exams/${examId}/marks/sheet?exam_subject_id=${paperId}&section_id=${sectionId}`,
  ).then((r) => r.data);
}

/**
 * Save the whole sheet as drafts; a blank row clears that student's mark.
 * Row errors come back keyed `records.N.field` — see ApiRequestError.
 */
export function saveSheet(
  examId: number,
  paperId: number,
  sectionId: number,
  records: MarkRecordInput[],
): Promise<MarkSheet> {
  return authedFetch<{ data: MarkSheet }>(`/exams/${examId}/marks/bulk`, {
    method: "POST",
    body: {
      ...sheetBody(paperId, sectionId),
      records: records.map((r) => ({ ...r })),
    },
  }).then((r) => r.data);
}

/** Lock the sheet for results (every student needs marks or absent). */
export function submitSheet(
  examId: number,
  paperId: number,
  sectionId: number,
): Promise<MarkSheet> {
  return authedFetch<{ data: MarkSheet }>(`/exams/${examId}/marks/submit`, {
    method: "POST",
    body: sheetBody(paperId, sectionId),
  }).then((r) => r.data);
}

/** Back to drafts for corrections (needs exam.edit). */
export function reopenSheet(
  examId: number,
  paperId: number,
  sectionId: number,
): Promise<MarkSheet> {
  return authedFetch<{ data: MarkSheet }>(`/exams/${examId}/marks/reopen`, {
    method: "POST",
    body: sheetBody(paperId, sectionId),
  }).then((r) => r.data);
}

/** Change one student's draft marks. */
export function updateMark(
  id: number,
  input: Omit<MarkRecordInput, "student_id">,
): Promise<Mark> {
  return authedFetch<{ data: Mark }>(`/marks/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}
