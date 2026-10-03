import { authedFetch } from "@/services/authService";
import type { AcademicYear, AcademicYearInput } from "@/types/academicYear";
import type { Paginated } from "@/types/api";

export function listAcademicYears(
  page = 1,
  perPage = 25,
): Promise<Paginated<AcademicYear>> {
  return authedFetch<Paginated<AcademicYear>>(
    `/academic-years?page=${page}&per_page=${perPage}`,
  );
}

export function createAcademicYear(
  input: AcademicYearInput,
): Promise<AcademicYear> {
  return authedFetch<{ data: AcademicYear }>("/academic-years", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateAcademicYear(
  id: number,
  input: AcademicYearInput,
): Promise<AcademicYear> {
  return authedFetch<{ data: AcademicYear }>(`/academic-years/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

/** Atomically makes this the single current year. */
export function setCurrentAcademicYear(id: number): Promise<AcademicYear> {
  return authedFetch<{ data: AcademicYear }>(
    `/academic-years/${id}/set-current`,
    { method: "POST" },
  ).then((r) => r.data);
}

export function deleteAcademicYear(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/academic-years/${id}`, { method: "DELETE" });
}
