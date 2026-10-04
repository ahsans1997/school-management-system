"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as subjectService from "@/services/subjectService";
import type { Paginated } from "@/types/api";
import {
  SUBJECT_TYPES,
  type Subject,
  type SubjectInput,
  type SubjectType,
} from "@/types/subject";

/** Form fields as typed by the user (the select uses "" for "none"). */
interface Draft {
  name: string;
  code: string;
  type: SubjectType | "";
}

const EMPTY: Draft = { name: "", code: "", type: "" };

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

function toInput(draft: Draft): SubjectInput {
  return {
    name: draft.name.trim(),
    code: draft.code.trim() || null,
    type: draft.type || null,
  };
}

function toDraft(s: Subject): Draft {
  return { name: s.name, code: s.code ?? "", type: s.type ?? "" };
}

function TypeSelect({
  value,
  onChange,
}: {
  value: SubjectType | "";
  onChange: (value: SubjectType | "") => void;
}) {
  return (
    <select
      aria-label="Type"
      value={value}
      onChange={(e) => onChange(e.target.value as SubjectType | "")}
      className={inputClass}
    >
      <option value="">No type</option>
      {SUBJECT_TYPES.map((t) => (
        <option key={t.value} value={t.value}>
          {t.label}
        </option>
      ))}
    </select>
  );
}

/** The school-wide subject catalogue: list, create, inline edit, delete. */
export function SubjectsManager() {
  const { can } = useAuth();
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [form, setForm] = useState<Draft>(EMPTY);
  const [editing, setEditing] = useState<(Draft & { id: number }) | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const canCreate = can("subject.create");
  const canEdit = can("subject.edit");
  const canDelete = can("subject.delete");

  const applyPage = useCallback((res: Paginated<Subject>) => {
    setSubjects(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  const load = useCallback(
    async (target: number) => {
      try {
        applyPage(await subjectService.listSubjects(target));
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    subjectService
      .listSubjects(1)
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

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const input = toInput(form);
    if (
      await run(
        () => subjectService.createSubject(input),
        `${input.name} created.`,
      )
    ) {
      setForm(EMPTY);
      await load(page);
    }
  }

  async function handleSaveEdit() {
    if (!editing) return;
    const { id, ...draft } = editing;
    if (
      await run(
        () => subjectService.updateSubject(id, toInput(draft)),
        "Subject updated.",
      )
    ) {
      setEditing(null);
      await load(page);
    }
  }

  async function handleDelete(s: Subject) {
    if (!window.confirm(`Delete ${s.name}? This cannot be undone.`)) return;
    if (
      await run(() => subjectService.deleteSubject(s.id), `${s.name} deleted.`)
    ) {
      // Step back if that emptied the last page.
      await load(subjects.length === 1 && page > 1 ? page - 1 : page);
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-4xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Subjects</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        The subject catalogue. Assign subjects to a class from the Classes page.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      {canCreate && (
        <form
          onSubmit={handleCreate}
          className="mb-6 grid gap-2 rounded-lg border border-gray-200 p-4 sm:grid-cols-[1fr_7rem_auto_auto] sm:items-end dark:border-gray-800"
        >
          <label className="text-sm">
            <span className="mb-1 block font-medium">Name</span>
            <input
              required
              maxLength={100}
              placeholder="e.g. Mathematics"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className={`w-full ${inputClass}`}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Code</span>
            <input
              maxLength={20}
              placeholder="Optional"
              value={form.code}
              onChange={(e) => setForm({ ...form, code: e.target.value })}
              className={`w-full ${inputClass}`}
            />
          </label>
          <TypeSelect
            value={form.type}
            onChange={(type) => setForm({ ...form, type })}
          />
          <button
            type="submit"
            disabled={busy}
            className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
          >
            Add subject
          </button>
        </form>
      )}

      {subjects.length === 0 ? (
        <p className="text-sm text-gray-500">No subjects yet.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th className="py-2 pr-3 font-medium">Name</th>
                <th className="py-2 pr-3 font-medium">Code</th>
                <th className="py-2 pr-3 font-medium">Type</th>
                <th className="py-2 pr-3 font-medium">Taught in</th>
                <th className="py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {subjects.map((s) =>
                editing?.id === s.id ? (
                  <tr
                    key={s.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="py-2 pr-2">
                      <input
                        aria-label="Name"
                        maxLength={100}
                        value={editing.name}
                        onChange={(e) =>
                          setEditing({ ...editing, name: e.target.value })
                        }
                        className={`w-full ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-2">
                      <input
                        aria-label="Code"
                        maxLength={20}
                        value={editing.code}
                        onChange={(e) =>
                          setEditing({ ...editing, code: e.target.value })
                        }
                        className={`w-20 ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-2">
                      <TypeSelect
                        value={editing.type}
                        onChange={(type) => setEditing({ ...editing, type })}
                      />
                    </td>
                    <td className="py-2 pr-3 tabular-nums">
                      {s.classes_count ?? "—"}
                    </td>
                    <td className="space-x-3 py-2 text-right whitespace-nowrap">
                      <button
                        type="button"
                        disabled={busy}
                        onClick={handleSaveEdit}
                        className="text-xs font-medium hover:underline"
                      >
                        Save
                      </button>
                      <button
                        type="button"
                        onClick={() => setEditing(null)}
                        className="text-xs text-gray-500 hover:underline"
                      >
                        Cancel
                      </button>
                    </td>
                  </tr>
                ) : (
                  <tr
                    key={s.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="py-2 pr-3 font-medium">{s.name}</td>
                    <td className="py-2 pr-3 tabular-nums">{s.code ?? "—"}</td>
                    <td className="py-2 pr-3">
                      {labelOf(SUBJECT_TYPES, s.type)}
                    </td>
                    <td className="py-2 pr-3 tabular-nums">
                      {s.classes_count === 1
                        ? "1 class"
                        : `${s.classes_count ?? 0} classes`}
                    </td>
                    <td className="space-x-3 py-2 text-right whitespace-nowrap">
                      {canEdit && (
                        <button
                          type="button"
                          onClick={() =>
                            setEditing({ ...toDraft(s), id: s.id })
                          }
                          className="text-xs text-gray-600 hover:underline dark:text-gray-300"
                        >
                          Edit
                        </button>
                      )}
                      {canDelete && (
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => handleDelete(s)}
                          className="text-xs text-red-600 hover:underline"
                        >
                          Delete
                        </button>
                      )}
                    </td>
                  </tr>
                ),
              )}
            </tbody>
          </table>
        </div>
      )}

      <Pagination page={page} lastPage={lastPage} onPage={load} />
    </div>
  );
}
