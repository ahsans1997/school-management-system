"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { ApiRequestError } from "@/services/apiClient";
import * as roleService from "@/services/roleService";
import type { PermissionGroup, Role } from "@/types/role";

function messageOf(error: unknown): string {
  if (error instanceof ApiRequestError) {
    const fieldError = error.error.errors
      ? Object.values(error.error.errors)[0]?.[0]
      : undefined;
    return fieldError ?? error.error.message;
  }
  return "Something went wrong.";
}

export function RolesManager() {
  const { can } = useAuth();
  const [roles, setRoles] = useState<Role[]>([]);
  const [groups, setGroups] = useState<PermissionGroup[]>([]);
  const [selected, setSelected] = useState<Role | null>(null);
  const [checked, setChecked] = useState<Set<string>>(new Set());
  const [newName, setNewName] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        const [rolesRes, groupsRes] = await Promise.all([
          roleService.listRoles(1, 100),
          roleService.listPermissionGroups(),
        ]);
        if (!active) return;
        setRoles(rolesRes.data);
        setGroups(groupsRes);
      } catch (err) {
        if (active) setError(messageOf(err));
      } finally {
        if (active) setLoading(false);
      }
    })();
    return () => {
      active = false;
    };
  }, []);

  const selectRole = useCallback(async (role: Role) => {
    setError(null);
    setNotice(null);
    try {
      const full = await roleService.getRole(role.id);
      setSelected(full);
      setChecked(new Set(full.permissions ?? []));
    } catch (err) {
      setError(messageOf(err));
    }
  }, []);

  const adminLocked = selected?.name === "Admin";
  const canEdit = can("role.edit") && !adminLocked;

  function toggle(permission: string) {
    setChecked((prev) => {
      const next = new Set(prev);
      if (next.has(permission)) {
        next.delete(permission);
      } else {
        next.add(permission);
      }
      return next;
    });
  }

  async function save() {
    if (!selected) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await roleService.syncRolePermissions(selected.id, [
        ...checked,
      ]);
      setSelected(updated);
      setRoles((rs) => rs.map((r) => (r.id === updated.id ? updated : r)));
      setNotice("Permissions saved.");
    } catch (err) {
      setError(messageOf(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!newName.trim()) return;
    setError(null);
    setNotice(null);
    try {
      const role = await roleService.createRole(newName.trim(), []);
      setRoles((rs) => [role, ...rs]);
      setNewName("");
      await selectRole(role);
    } catch (err) {
      setError(messageOf(err));
    }
  }

  async function remove(role: Role) {
    setError(null);
    setNotice(null);
    try {
      await roleService.deleteRole(role.id);
      setRoles((rs) => rs.filter((r) => r.id !== role.id));
      if (selected?.id === role.id) {
        setSelected(null);
        setChecked(new Set());
      }
    } catch (err) {
      setError(messageOf(err));
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-6 text-2xl font-semibold">Roles &amp; Permissions</h1>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      <div className="grid gap-6 md:grid-cols-[260px_1fr]">
        {/* Roles list */}
        <aside className="space-y-3">
          {can("role.create") && (
            <form onSubmit={handleCreate} className="flex gap-2">
              <input
                aria-label="New role name"
                placeholder="New role name"
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
              />
              <button
                type="submit"
                className="rounded-md bg-gray-900 px-3 py-1 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
              >
                Add
              </button>
            </form>
          )}

          <ul className="divide-y divide-gray-200 rounded-md border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            {roles.map((role) => (
              <li
                key={role.id}
                className={`flex items-center justify-between px-3 py-2 text-sm ${
                  selected?.id === role.id ? "bg-gray-100 dark:bg-gray-800" : ""
                }`}
              >
                <button
                  type="button"
                  onClick={() => selectRole(role)}
                  className="text-left"
                >
                  {role.name}
                  {role.is_system && (
                    <span className="ml-2 rounded bg-gray-200 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                      system
                    </span>
                  )}
                </button>
                {can("role.delete") && !role.is_system && (
                  <button
                    type="button"
                    onClick={() => remove(role)}
                    className="text-xs text-red-600 hover:underline"
                  >
                    delete
                  </button>
                )}
              </li>
            ))}
          </ul>
        </aside>

        {/* Permission editor */}
        <section>
          {!selected ? (
            <p className="text-sm text-gray-500">
              Select a role to view and edit its permissions.
            </p>
          ) : (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h2 className="text-lg font-medium">{selected.name}</h2>
                {canEdit && (
                  <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
                  >
                    {saving ? "Saving…" : "Save"}
                  </button>
                )}
              </div>

              {adminLocked && (
                <p className="text-sm text-gray-500">
                  The Admin role always holds every permission.
                </p>
              )}

              <div className="grid gap-4 sm:grid-cols-2">
                {groups.map((group) => (
                  <fieldset
                    key={group.group}
                    className="rounded-md border border-gray-200 p-3 dark:border-gray-800"
                  >
                    <legend className="px-1 text-xs font-semibold text-gray-500 uppercase">
                      {group.group}
                    </legend>
                    <div className="space-y-1">
                      {group.permissions.map((permission) => (
                        <label
                          key={permission}
                          className="flex items-center gap-2 text-sm"
                        >
                          <input
                            type="checkbox"
                            checked={checked.has(permission)}
                            disabled={!canEdit}
                            onChange={() => toggle(permission)}
                          />
                          {permission}
                        </label>
                      ))}
                    </div>
                  </fieldset>
                ))}
              </div>
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
