import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type { ManagedUser } from "@/types/user";

export interface CreateUserPayload {
  name: string;
  email: string;
  password: string;
  roles?: string[];
}

export function listUsers(
  page = 1,
  perPage = 25,
  search = "",
): Promise<Paginated<ManagedUser>> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });
  if (search) params.set("search", search);
  return authedFetch<Paginated<ManagedUser>>(`/users?${params.toString()}`);
}

export function createUser(payload: CreateUserPayload): Promise<ManagedUser> {
  return authedFetch<{ data: ManagedUser }>("/users", {
    method: "POST",
    body: { ...payload },
  }).then((r) => r.data);
}

export function assignRole(id: number, roles: string[]): Promise<ManagedUser> {
  return authedFetch<{ data: ManagedUser }>(`/users/${id}/assign-role`, {
    method: "POST",
    body: { roles },
  }).then((r) => r.data);
}

export function toggleStatus(id: number): Promise<ManagedUser> {
  return authedFetch<{ data: ManagedUser }>(`/users/${id}/toggle-status`, {
    method: "PATCH",
  }).then((r) => r.data);
}

export function resetPassword(
  id: number,
  password?: string,
): Promise<{ message: string; password?: string }> {
  return authedFetch<{ message: string; password?: string }>(
    `/users/${id}/reset-password`,
    { method: "POST", body: password ? { password } : {} },
  );
}

export function deleteUser(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/users/${id}`, { method: "DELETE" });
}
