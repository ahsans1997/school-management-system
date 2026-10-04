import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type {
  ClassSubject,
  ClassSubjectPage,
  Subject,
  SubjectInput,
} from "@/types/subject";

/** The subject catalogue, ordered by name. The API caps perPage at 100. */
export function listSubjects(
  page = 1,
  perPage = 25,
): Promise<Paginated<Subject>> {
  return authedFetch<Paginated<Subject>>(
    `/subjects?page=${page}&per_page=${perPage}`,
  );
}

export function createSubject(input: SubjectInput): Promise<Subject> {
  return authedFetch<{ data: Subject }>("/subjects", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateSubject(
  id: number,
  input: SubjectInput,
): Promise<Subject> {
  return authedFetch<{ data: Subject }>(`/subjects/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

export function deleteSubject(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/subjects/${id}`, { method: "DELETE" });
}

/** Subjects taught in one class, ordered by subject name, with the class. */
export function listClassSubjects(
  classId: number,
  page = 1,
  perPage = 25,
): Promise<ClassSubjectPage> {
  return authedFetch<ClassSubjectPage>(
    `/classes/${classId}/subjects?page=${page}&per_page=${perPage}`,
  );
}

export function assignSubject(
  classId: number,
  subjectId: number,
): Promise<ClassSubject> {
  return authedFetch<{ data: ClassSubject }>(`/classes/${classId}/subjects`, {
    method: "POST",
    body: { subject_id: subjectId },
  }).then((r) => r.data);
}

export function unassignSubject(
  classId: number,
  subjectId: number,
): Promise<unknown> {
  return authedFetch<unknown>(`/classes/${classId}/subjects/${subjectId}`, {
    method: "DELETE",
  });
}
