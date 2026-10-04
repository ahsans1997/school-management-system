"use client";

import {
  useCallback,
  useEffect,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import * as guardianService from "@/services/guardianService";
import type { Paginated } from "@/types/api";
import type { AccountInput, Guardian, GuardianInput } from "@/types/guardian";

/** Profile fields as typed by the user. */
interface Draft {
  name: string;
  phone: string;
  email: string;
  occupation: string;
  address: string;
}

const EMPTY: Draft = {
  name: "",
  phone: "",
  email: "",
  occupation: "",
  address: "",
};
const NO_ACCOUNT: AccountInput = { email: "", password: "" };

const inputClass =
  "w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";
const primaryButton =
  "rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900";

const orNull = (value: string) => value.trim() || null;

function toInput(draft: Draft): GuardianInput {
  return {
    name: draft.name.trim(),
    phone: draft.phone.trim(),
    email: orNull(draft.email),
    occupation: orNull(draft.occupation),
    address: orNull(draft.address),
  };
}

function toDraft(g: Guardian): Draft {
  return {
    name: g.name,
    phone: g.phone,
    email: g.email ?? "",
    occupation: g.occupation ?? "",
    address: g.address ?? "",
  };
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="text-sm">
      <span className="mb-1 block font-medium">{label}</span>
      {children}
    </label>
  );
}

/** Login email + initial password, shared by both "create login" paths. */
function AccountFields({
  value,
  onChange,
}: {
  value: AccountInput;
  onChange: (value: AccountInput) => void;
}) {
  return (
    <>
      <Field label="Login email">
        <input
          type="email"
          required
          maxLength={255}
          autoComplete="off"
          value={value.email}
          onChange={(e) => onChange({ ...value, email: e.target.value })}
          className={inputClass}
        />
      </Field>
      <Field label="Initial password">
        <input
          type="password"
          required
          minLength={8}
          autoComplete="new-password"
          value={value.password}
          onChange={(e) => onChange({ ...value, password: e.target.value })}
          className={inputClass}
        />
      </Field>
    </>
  );
}

/** The guardian register: search, create (optionally with a Parent login), edit, delete. */
export function GuardiansManager() {
  const { can } = useAuth();
  const [guardians, setGuardians] = useState<Guardian[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState(""); // the search the list reflects
  const [form, setForm] = useState<Draft>(EMPTY);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [withLogin, setWithLogin] = useState(false);
  const [account, setAccount] = useState<AccountInput>(NO_ACCOUNT);
  const [accountFor, setAccountFor] = useState<Guardian | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const canCreate = can("guardian.create");
  const canEdit = can("guardian.edit"); // also needed to create logins
  const canDelete = can("guardian.delete");
  const showForm = editingId !== null ? canEdit : canCreate;

  const applyPage = useCallback((res: Paginated<Guardian>) => {
    setGuardians(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  const load = useCallback(
    async (target: number, term = query) => {
      try {
        applyPage(await guardianService.listGuardians(target, term));
        setQuery(term);
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage, query],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    guardianService
      .listGuardians(1)
      .then((res) => {
        if (active) applyPage(res);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [applyPage]);

  async function run(task: () => Promise<unknown>, success: string) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await task();
      setNotice(success);
      return true;
    } catch (err) {
      setError(errorMessage(err));
      return false;
    } finally {
      setBusy(false);
    }
  }

  function resetForm() {
    setForm(EMPTY);
    setEditingId(null);
    setWithLogin(false);
    setAccount(NO_ACCOUNT);
  }

  function startEdit(g: Guardian) {
    setAccountFor(null);
    setWithLogin(false);
    setForm(toDraft(g));
    setEditingId(g.id);
  }

  function startAccount(g: Guardian) {
    resetForm();
    setAccount({ email: g.email ?? "", password: "" });
    setAccountFor(g);
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const input = toInput(form);
    const ok =
      editingId !== null
        ? await run(
            () => guardianService.updateGuardian(editingId, input),
            `${input.name} updated.`,
          )
        : await run(
            () =>
              guardianService.createGuardian(
                input,
                withLogin ? account : undefined,
              ),
            withLogin
              ? `${input.name} created with a parent login.`
              : `${input.name} created.`,
          );
    if (ok) {
      resetForm();
      await load(page);
    }
  }

  async function handleCreateAccount(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!accountFor) return;
    if (
      await run(
        () => guardianService.createGuardianAccount(accountFor.id, account),
        `Parent login created for ${accountFor.name}.`,
      )
    ) {
      setAccountFor(null);
      setAccount(NO_ACCOUNT);
      await load(page);
    }
  }

  async function handleDelete(g: Guardian) {
    if (!window.confirm(`Delete ${g.name}? This cannot be undone.`)) return;
    if (
      await run(
        () => guardianService.deleteGuardian(g.id),
        `${g.name} deleted.`,
      )
    ) {
      if (editingId === g.id) resetForm();
      // Step back if that emptied the last page.
      await load(guardians.length === 1 && page > 1 ? page - 1 : page);
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Guardians</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Parents and guardians. Search by phone before adding one, so the same
        person isn&apos;t entered twice. Children are linked from the student
        record.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      {showForm && (
        <form
          onSubmit={handleSubmit}
          aria-label={editingId !== null ? "Edit guardian" : "Add guardian"}
          className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
        >
          <h2 className="mb-3 text-sm font-semibold">
            {editingId !== null
              ? `Edit ${form.name || "guardian"}`
              : "Add a guardian"}
          </h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Name">
              <input
                required
                maxLength={100}
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Phone">
              <input
                type="tel"
                required
                maxLength={20}
                placeholder="e.g. 01712-345678"
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Email (optional)">
              <input
                type="email"
                maxLength={255}
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                className={inputClass}
              />
            </Field>
            <Field label="Occupation (optional)">
              <input
                maxLength={100}
                value={form.occupation}
                onChange={(e) =>
                  setForm({ ...form, occupation: e.target.value })
                }
                className={inputClass}
              />
            </Field>
            <div className="sm:col-span-2">
              <Field label="Address (optional)">
                <input
                  maxLength={500}
                  value={form.address}
                  onChange={(e) =>
                    setForm({ ...form, address: e.target.value })
                  }
                  className={inputClass}
                />
              </Field>
            </div>
          </div>

          {editingId === null && canEdit && (
            <div className="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={withLogin}
                  onChange={(e) => {
                    setWithLogin(e.target.checked);
                    if (e.target.checked && !account.email)
                      setAccount({ ...account, email: form.email });
                  }}
                />
                Also create a parent-portal login
              </label>
              {withLogin && (
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  <AccountFields value={account} onChange={setAccount} />
                </div>
              )}
            </div>
          )}

          <div className="mt-4 flex gap-3">
            <button type="submit" disabled={busy} className={primaryButton}>
              {editingId !== null ? "Save changes" : "Add guardian"}
            </button>
            {editingId !== null && (
              <button
                type="button"
                onClick={resetForm}
                className="text-sm text-gray-500 hover:underline"
              >
                Cancel
              </button>
            )}
          </div>
        </form>
      )}

      {accountFor && (
        <form
          onSubmit={handleCreateAccount}
          aria-label="Create parent login"
          className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
        >
          <h2 className="mb-3 text-sm font-semibold">
            Parent login for {accountFor.name}
          </h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <AccountFields value={account} onChange={setAccount} />
          </div>
          <div className="mt-4 flex gap-3">
            <button type="submit" disabled={busy} className={primaryButton}>
              Create login
            </button>
            <button
              type="button"
              onClick={() => setAccountFor(null)}
              className="text-sm text-gray-500 hover:underline"
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      <form
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          void load(1, search.trim());
        }}
        className="mb-4 flex gap-2"
      >
        <input
          type="search"
          aria-label="Search guardians"
          placeholder="Search name, phone or email"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className={`max-w-xs ${inputClass}`}
        />
        <button
          type="submit"
          className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
        >
          Search
        </button>
      </form>

      {guardians.length === 0 ? (
        <p className="text-sm text-gray-500">
          {query ? `No guardians match “${query}”.` : "No guardians yet."}
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th className="py-2 pr-3 font-medium">Name</th>
                <th className="py-2 pr-3 font-medium">Phone</th>
                <th className="py-2 pr-3 font-medium">Email</th>
                <th className="py-2 pr-3 font-medium">Occupation</th>
                <th className="py-2 pr-3 font-medium">Parent login</th>
                <th className="py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {guardians.map((g) => (
                <tr
                  key={g.id}
                  className="border-b border-gray-100 dark:border-gray-900"
                >
                  <td className="py-2 pr-3 font-medium">{g.name}</td>
                  <td className="py-2 pr-3 whitespace-nowrap tabular-nums">
                    {g.phone}
                  </td>
                  <td className="py-2 pr-3">{g.email ?? "—"}</td>
                  <td className="py-2 pr-3">{g.occupation ?? "—"}</td>
                  <td className="py-2 pr-3">
                    {g.account ? (
                      <span>
                        {g.account.email}
                        {!g.account.is_active && (
                          <span className="ml-1 text-xs text-red-600">
                            (inactive)
                          </span>
                        )}
                      </span>
                    ) : canEdit ? (
                      <button
                        type="button"
                        onClick={() => startAccount(g)}
                        className="text-xs hover:underline"
                      >
                        Create login
                      </button>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="space-x-3 py-2 text-right whitespace-nowrap">
                    {canEdit && (
                      <button
                        type="button"
                        onClick={() => startEdit(g)}
                        className="text-xs text-gray-600 hover:underline dark:text-gray-300"
                      >
                        Edit
                      </button>
                    )}
                    {canDelete && (
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => handleDelete(g)}
                        className="text-xs text-red-600 hover:underline"
                      >
                        Delete
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Pagination
        page={page}
        lastPage={lastPage}
        onPage={(target) => load(target)}
      />
    </div>
  );
}
