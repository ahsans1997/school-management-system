"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { ApiRequestError } from "@/services/apiClient";
import * as roleService from "@/services/roleService";
import * as userService from "@/services/userService";
import type { ManagedUser } from "@/types/user";

function messageOf(error: unknown): string {
  if (error instanceof ApiRequestError) {
    const fieldError = error.error.errors
      ? Object.values(error.error.errors)[0]?.[0]
      : undefined;
    return fieldError ?? error.error.message;
  }
  return "Something went wrong.";
}

const EMPTY_FORM = { name: "", email: "", password: "", role: "" };

export function UsersManager() {
  const { user: currentUser, can } = useAuth();
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [roleNames, setRoleNames] = useState<string[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [search, setSearch] = useState("");
  const [form, setForm] = useState(EMPTY_FORM);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const loadUsers = useCallback(async (targetPage: number, term: string) => {
    setError(null);
    try {
      const res = await userService.listUsers(targetPage, 25, term);
      setUsers(res.data);
      setPage(res.meta.current_page);
      setLastPage(res.meta.last_page);
    } catch (err) {
      setError(messageOf(err));
    }
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const roles = await roleService.listRoles(1, 100);
        setRoleNames(roles.data.map((r) => r.name));
      } catch {
        /* role list is optional for viewing users */
      }
      await loadUsers(1, "");
      setLoading(false);
    })();
  }, [loadUsers]);

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setNotice(null);
    try {
      await userService.createUser({
        name: form.name,
        email: form.email,
        password: form.password,
        roles: form.role ? [form.role] : [],
      });
      setForm(EMPTY_FORM);
      setNotice("User created.");
      await loadUsers(1, search);
    } catch (err) {
      setError(messageOf(err));
    }
  }

  async function changeRole(target: ManagedUser, role: string) {
    setError(null);
    setNotice(null);
    try {
      const updated = await userService.assignRole(
        target.id,
        role ? [role] : [],
      );
      setUsers((us) => us.map((u) => (u.id === updated.id ? updated : u)));
    } catch (err) {
      setError(messageOf(err));
    }
  }

  async function toggle(target: ManagedUser) {
    setError(null);
    setNotice(null);
    try {
      const updated = await userService.toggleStatus(target.id);
      setUsers((us) => us.map((u) => (u.id === updated.id ? updated : u)));
    } catch (err) {
      setError(messageOf(err));
    }
  }

  async function reset(target: ManagedUser) {
    if (!window.confirm(`Reset password for ${target.email}?`)) return;
    setError(null);
    setNotice(null);
    try {
      const res = await userService.resetPassword(target.id);
      setNotice(
        res.password
          ? `Temporary password for ${target.email}: ${res.password}`
          : "Password reset.",
      );
    } catch (err) {
      setError(messageOf(err));
    }
  }

  async function remove(target: ManagedUser) {
    if (!window.confirm(`Delete ${target.email}?`)) return;
    setError(null);
    setNotice(null);
    try {
      await userService.deleteUser(target.id);
      setUsers((us) => us.filter((u) => u.id !== target.id));
    } catch (err) {
      setError(messageOf(err));
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  const canEdit = can("user.edit");
  const canDelete = can("user.delete");

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-6 text-2xl font-semibold">Users</h1>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      {can("user.create") && (
        <form
          onSubmit={handleCreate}
          className="mb-6 grid gap-2 rounded-md border border-gray-200 p-4 sm:grid-cols-5 dark:border-gray-800"
        >
          <input
            aria-label="Name"
            placeholder="Name"
            required
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            className="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
          />
          <input
            aria-label="Email"
            type="email"
            placeholder="Email"
            required
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            className="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
          />
          <input
            aria-label="Password"
            type="password"
            placeholder="Password"
            required
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            className="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
          />
          <select
            aria-label="Role"
            value={form.role}
            onChange={(e) => setForm({ ...form, role: e.target.value })}
            className="rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
          >
            <option value="">No role</option>
            {roleNames.map((name) => (
              <option key={name} value={name}>
                {name}
              </option>
            ))}
          </select>
          <button
            type="submit"
            className="rounded-md bg-gray-900 px-3 py-1 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
          >
            Create
          </button>
        </form>
      )}

      <div className="mb-3 flex gap-2">
        <input
          aria-label="Search users"
          placeholder="Search name or email…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-64 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800"
        />
        <button
          type="button"
          onClick={() => loadUsers(1, search)}
          className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
        >
          Search
        </button>
      </div>

      <table className="w-full border-collapse text-sm">
        <thead>
          <tr className="border-b border-gray-200 text-left dark:border-gray-800">
            <th className="py-2">Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th className="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          {users.map((u) => {
            const isSelf = u.id === currentUser?.id;
            return (
              <tr
                key={u.id}
                className="border-b border-gray-100 dark:border-gray-900"
              >
                <td className="py-2">{u.name}</td>
                <td>{u.email}</td>
                <td>
                  {canEdit && !isSelf ? (
                    <select
                      aria-label={`Role for ${u.email}`}
                      value={u.roles[0] ?? ""}
                      onChange={(e) => changeRole(u, e.target.value)}
                      className="rounded-md border border-gray-300 px-1 py-0.5 text-xs dark:border-gray-700 dark:bg-gray-800"
                    >
                      <option value="">—</option>
                      {roleNames.map((name) => (
                        <option key={name} value={name}>
                          {name}
                        </option>
                      ))}
                    </select>
                  ) : (
                    u.roles.join(", ") || "—"
                  )}
                </td>
                <td>
                  <span
                    className={u.is_active ? "text-green-600" : "text-gray-400"}
                  >
                    {u.is_active ? "active" : "disabled"}
                  </span>
                </td>
                <td className="space-x-3 py-2 text-right">
                  {canEdit && !isSelf && (
                    <button
                      type="button"
                      onClick={() => toggle(u)}
                      className="text-xs text-gray-600 hover:underline dark:text-gray-300"
                    >
                      {u.is_active ? "deactivate" : "activate"}
                    </button>
                  )}
                  {canEdit && (
                    <button
                      type="button"
                      onClick={() => reset(u)}
                      className="text-xs text-gray-600 hover:underline dark:text-gray-300"
                    >
                      reset pw
                    </button>
                  )}
                  {canDelete && !isSelf && (
                    <button
                      type="button"
                      onClick={() => remove(u)}
                      className="text-xs text-red-600 hover:underline"
                    >
                      delete
                    </button>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>

      <div className="mt-4 flex items-center gap-3 text-sm">
        <button
          type="button"
          disabled={page <= 1}
          onClick={() => loadUsers(page - 1, search)}
          className="rounded-md border border-gray-300 px-3 py-1 disabled:opacity-50 dark:border-gray-700"
        >
          Prev
        </button>
        <span className="text-gray-500">
          Page {page} of {lastPage}
        </span>
        <button
          type="button"
          disabled={page >= lastPage}
          onClick={() => loadUsers(page + 1, search)}
          className="rounded-md border border-gray-300 px-3 py-1 disabled:opacity-50 dark:border-gray-700"
        >
          Next
        </button>
      </div>
    </div>
  );
}
