import { authedFetch } from "@/services/authService";
import type { Paginated } from "@/types/api";
import type { PermissionGroup, Role } from "@/types/role";

export function listRoles(page = 1, perPage = 25): Promise<Paginated<Role>> {
  return authedFetch<Paginated<Role>>(
    `/roles?page=${page}&per_page=${perPage}`,
  );
}

export function getRole(id: number): Promise<Role> {
  return authedFetch<{ data: Role }>(`/roles/${id}`).then((r) => r.data);
}

export function createRole(name: string, permissions: string[]): Promise<Role> {
  return authedFetch<{ data: Role }>("/roles", {
    method: "POST",
    body: { name, permissions },
  }).then((r) => r.data);
}

export function syncRolePermissions(
  id: number,
  permissions: string[],
): Promise<Role> {
  return authedFetch<{ data: Role }>(`/roles/${id}/permissions`, {
    method: "PUT",
    body: { permissions },
  }).then((r) => r.data);
}

export function deleteRole(id: number): Promise<unknown> {
  return authedFetch<unknown>(`/roles/${id}`, { method: "DELETE" });
}

export function listPermissionGroups(): Promise<PermissionGroup[]> {
  return authedFetch<{ data: PermissionGroup[] }>("/permissions").then(
    (r) => r.data,
  );
}
