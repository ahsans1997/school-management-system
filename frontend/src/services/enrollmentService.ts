import { authedFetch } from "@/services/authService";
import type {
  Enrollment,
  EnrollmentChange,
  EnrollmentPage,
} from "@/types/enrollment";

export interface EnrollmentQuery {
  academicYearId?: number | null;
  classId?: number | null;
  sectionId?: number | null;
  search?: string;
  page?: number;
  perPage?: number;
}

/** Ordered by class, section, roll. Defaults to the current year. */
export function listEnrollments({
  academicYearId,
  classId,
  sectionId,
  search = "",
  page = 1,
  perPage = 100,
}: EnrollmentQuery = {}): Promise<EnrollmentPage> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (academicYearId) params.set("academic_year_id", String(academicYearId));
  if (classId) params.set("class_id", String(classId));
  if (sectionId) params.set("section_id", String(sectionId));
  if (search) params.set("search", search);
  return authedFetch<EnrollmentPage>(`/enrollments?${params.toString()}`);
}

/** Capacity, roll and one-per-year are checked by the API (422 with a message). */
export function enroll(
  studentId: number,
  academicYearId: number,
  sectionId: number,
  roll: number | null,
): Promise<Enrollment> {
  return authedFetch<{ data: Enrollment }>("/enrollments", {
    method: "POST",
    body: {
      student_id: studentId,
      academic_year_id: academicYearId,
      section_id: sectionId,
      roll,
    },
  }).then((r) => r.data);
}

export function updateEnrollment(
  id: number,
  change: EnrollmentChange,
): Promise<Enrollment> {
  return authedFetch<{ data: Enrollment }>(`/enrollments/${id}`, {
    method: "PUT",
    body: { ...change },
  }).then((r) => r.data);
}

/** For mistakes only — leaving the school is a status change. */
export function deleteEnrollment(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/enrollments/${id}`, { method: "DELETE" });
}

export interface PromotePayload {
  from_year_id: number;
  from_section_id: number;
  to_year_id: number;
  to_section_id: number;
  student_ids: number[];
}

/** All or nothing; returns the new enrollments. */
export function promote(
  payload: PromotePayload,
): Promise<{ data: Enrollment[]; promoted: number }> {
  return authedFetch<{ data: Enrollment[]; promoted: number }>(
    "/enrollments/promote",
    { method: "POST", body: { ...payload } },
  );
}
