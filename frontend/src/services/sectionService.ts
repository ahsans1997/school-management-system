import { authedFetch } from "@/services/authService";
import type { Section, SectionInput, SectionPage } from "@/types/section";

/** Sections of one class, ordered by name, with the class itself. */
export function listSections(
  classId: number,
  page = 1,
  perPage = 25,
): Promise<SectionPage> {
  return authedFetch<SectionPage>(
    `/classes/${classId}/sections?page=${page}&per_page=${perPage}`,
  );
}

export function createSection(
  classId: number,
  input: SectionInput,
): Promise<Section> {
  return authedFetch<{ data: Section }>(`/classes/${classId}/sections`, {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateSection(
  id: number,
  input: SectionInput,
): Promise<Section> {
  return authedFetch<{ data: Section }>(`/sections/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

export function deleteSection(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/sections/${id}`, { method: "DELETE" });
}
