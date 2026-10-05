import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type { StoredDocument } from "@/types/document";
import type { AccountInput } from "@/types/guardian";
import type { Teacher, TeacherInput } from "@/types/teacher";

export interface TeacherQuery {
  page?: number;
  search?: string;
  archived?: boolean;
  perPage?: number;
}

/** Ordered by name; `search` matches name, employee ID, phone or department. */
export function listTeachers({
  page = 1,
  search = "",
  archived = false,
  perPage = 25,
}: TeacherQuery = {}): Promise<Paginated<Teacher>> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (search) params.set("search", search);
  if (archived) params.set("archived", "1");
  return authedFetch<Paginated<Teacher>>(`/teachers?${params.toString()}`);
}

const data = (r: { data: Teacher }) => r.data;

/** Full profile: login, assignments, class-teacher sections, documents. */
export function getTeacher(id: number): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}`).then(data);
}

export function createTeacher(input: TeacherInput): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>("/teachers", {
    method: "POST",
    body: { ...input },
  }).then(data);
}

export function updateTeacher(
  id: number,
  input: TeacherInput,
): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then(data);
}

/** Archive; refused (409) while the teacher still has subjects or a class. */
export function archiveTeacher(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/teachers/${id}`, { method: "DELETE" });
}

export function restoreTeacher(id: number): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}/restore`, {
    method: "POST",
  }).then(data);
}

/** A Teacher-role login; the role is fixed by the server. */
export function createAccount(
  id: number,
  account: AccountInput,
): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}/account`, {
    method: "POST",
    body: { ...account },
  }).then(data);
}

export function uploadPhoto(id: number, photo: File): Promise<Teacher> {
  const form = new FormData();
  form.append("photo", photo);
  return authedFetch<{ data: Teacher }>(`/teachers/${id}/photo`, {
    method: "POST",
    body: form,
  }).then(data);
}

export function removePhoto(id: number): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}/photo`, {
    method: "DELETE",
  }).then(data);
}

export function uploadDocument(
  id: number,
  title: string,
  file: File,
): Promise<StoredDocument> {
  const form = new FormData();
  form.append("title", title);
  form.append("file", file);
  return authedFetch<{ data: StoredDocument }>(`/teachers/${id}/documents`, {
    method: "POST",
    body: form,
  }).then((r) => r.data);
}

export function deleteDocument(
  id: number,
  documentId: number,
): Promise<unknown> {
  return authedFetch<unknown>(`/teachers/${id}/documents/${documentId}`, {
    method: "DELETE",
  });
}

/** Teaching changes return the teacher with updated assignments + class-teacher sections. */
export function assignSubject(
  id: number,
  sectionId: number,
  subjectId: number,
): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}/subjects`, {
    method: "POST",
    body: { section_id: sectionId, subject_id: subjectId },
  }).then(data);
}

export function unassignSubject(
  id: number,
  assignmentId: number,
): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(
    `/teachers/${id}/subjects/${assignmentId}`,
    { method: "DELETE" },
  ).then(data);
}

export function setClassTeacher(
  id: number,
  sectionId: number,
): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(`/teachers/${id}/classes`, {
    method: "POST",
    body: { section_id: sectionId },
  }).then(data);
}

export function removeClassTeacher(
  id: number,
  sectionId: number,
): Promise<Teacher> {
  return authedFetch<{ data: Teacher }>(
    `/teachers/${id}/classes/${sectionId}`,
    { method: "DELETE" },
  ).then(data);
}
