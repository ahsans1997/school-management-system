import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type { SchoolClass, SchoolClassInput } from "@/types/schoolClass";

/** Ordered by numeric_level, then name. */
export function listClasses(
  page = 1,
  perPage = 25,
): Promise<Paginated<SchoolClass>> {
  return authedFetch<Paginated<SchoolClass>>(
    `/classes?page=${page}&per_page=${perPage}`,
  );
}

export function createClass(input: SchoolClassInput): Promise<SchoolClass> {
  return authedFetch<{ data: SchoolClass }>("/classes", {
    method: "POST",
    body: { ...input },
  }).then((r) => r.data);
}

export function updateClass(
  id: number,
  input: SchoolClassInput,
): Promise<SchoolClass> {
  return authedFetch<{ data: SchoolClass }>(`/classes/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

export function deleteClass(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/classes/${id}`, { method: "DELETE" });
}
