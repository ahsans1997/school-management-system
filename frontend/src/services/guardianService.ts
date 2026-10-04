import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type { AccountInput, Guardian, GuardianInput } from "@/types/guardian";

/** Ordered by name; `search` matches name, phone or email. */
export function listGuardians(
  page = 1,
  search = "",
  perPage = 25,
): Promise<Paginated<Guardian>> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (search) params.set("search", search);
  return authedFetch<Paginated<Guardian>>(`/guardians?${params.toString()}`);
}

/** With `account`, the guardian and its login are created together or not at all. */
export function createGuardian(
  input: GuardianInput,
  account?: AccountInput,
): Promise<Guardian> {
  return authedFetch<{ data: Guardian }>("/guardians", {
    method: "POST",
    body: account ? { ...input, account: { ...account } } : { ...input },
  }).then((r) => r.data);
}

export function updateGuardian(
  id: number,
  input: GuardianInput,
): Promise<Guardian> {
  return authedFetch<{ data: Guardian }>(`/guardians/${id}`, {
    method: "PUT",
    body: { ...input },
  }).then((r) => r.data);
}

export function deleteGuardian(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/guardians/${id}`, { method: "DELETE" });
}

/** Give an existing guardian a Parent-portal login. */
export function createGuardianAccount(
  id: number,
  account: AccountInput,
): Promise<Guardian> {
  return authedFetch<{ data: Guardian }>(`/guardians/${id}/account`, {
    method: "POST",
    body: { ...account },
  }).then((r) => r.data);
}
