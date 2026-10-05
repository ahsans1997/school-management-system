import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type {
  Relationship,
  Student,
  StudentDocument,
  StudentInput,
} from "@/types/student";

export interface StudentQuery {
  page?: number;
  search?: string;
  archived?: boolean;
  perPage?: number;
}

/** Ordered by name; `search` matches name, student ID, admission no. or phone. */
export function listStudents({
  page = 1,
  search = "",
  archived = false,
  perPage = 25,
}: StudentQuery = {}): Promise<Paginated<Student>> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (search) params.set("search", search);
  if (archived) params.set("archived", "1");
  return authedFetch<Paginated<Student>>(`/students?${params.toString()}`);
}

/** Full profile, incl. guardians and documents. Works for archived students. */
export function getStudent(id: number): Promise<Student> {
  return authedFetch<{ data: Student }>(`/students/${id}`).then((r) => r.data);
}

export function createStudent(input: StudentInput): Promise<Student> {
  return authedFetch<{ data: Student }>("/students", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateStudent(
  id: number,
  input: StudentInput,
): Promise<Student> {
  return authedFetch<{ data: Student }>(`/students/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

/** Archive (soft delete). */
export function archiveStudent(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/students/${id}`, { method: "DELETE" });
}

export function restoreStudent(id: number): Promise<Student> {
  return authedFetch<{ data: Student }>(`/students/${id}/restore`, {
    method: "POST",
  }).then((r) => r.data);
}

export function uploadPhoto(id: number, photo: File): Promise<Student> {
  const form = new FormData();
  form.append("photo", photo);
  return authedFetch<{ data: Student }>(`/students/${id}/photo`, {
    method: "POST",
    body: form,
  }).then((r) => r.data);
}

export function removePhoto(id: number): Promise<Student> {
  return authedFetch<{ data: Student }>(`/students/${id}/photo`, {
    method: "DELETE",
  }).then((r) => r.data);
}

export function uploadDocument(
  id: number,
  title: string,
  file: File,
): Promise<StudentDocument> {
  const form = new FormData();
  form.append("title", title);
  form.append("file", file);
  return authedFetch<{ data: StudentDocument }>(`/students/${id}/documents`, {
    method: "POST",
    body: form,
  }).then((r) => r.data);
}

export function deleteDocument(
  id: number,
  documentId: number,
): Promise<unknown> {
  return authedFetch<unknown>(`/students/${id}/documents/${documentId}`, {
    method: "DELETE",
  });
}

/** Each guardian-link call returns the student with its updated guardian list. */
export function linkGuardian(
  id: number,
  guardianId: number,
  relationship: Relationship,
  isPrimary: boolean,
): Promise<Student> {
  return authedFetch<{ data: Student }>(`/students/${id}/guardians`, {
    method: "POST",
    body: { guardian_id: guardianId, relationship, is_primary: isPrimary },
  }).then((r) => r.data);
}

export function updateGuardianLink(
  id: number,
  guardianId: number,
  relationship: Relationship,
  makePrimary?: boolean,
): Promise<Student> {
  return authedFetch<{ data: Student }>(
    `/students/${id}/guardians/${guardianId}`,
    {
      method: "PUT",
      body: makePrimary ? { relationship, is_primary: true } : { relationship },
    },
  ).then((r) => r.data);
}

export function unlinkGuardian(
  id: number,
  guardianId: number,
): Promise<Student> {
  return authedFetch<{ data: Student }>(
    `/students/${id}/guardians/${guardianId}`,
    { method: "DELETE" },
  ).then((r) => r.data);
}
