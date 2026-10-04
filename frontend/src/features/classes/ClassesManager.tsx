"use client";

import Link from "next/link";
import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as classService from "@/services/classService";
import type { Paginated } from "@/types/api";
import {
  MEDIUMS,
  SHIFTS,
  type Medium,
  type SchoolClass,
  type SchoolClassInput,
  type Shift,
} from "@/types/schoolClass";

/** Form fields as typed by the user (selects use "" for "none"). */
interface Draft {
  name: string;
  numeric_level: string;
  shift: Shift | "";
  medium: Medium | "";
}

const EMPTY: Draft = { name: "", numeric_level: "", shift: "", medium: "" };

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

function toInput(draft: Draft): SchoolClassInput {
  return {
    name: draft.name.trim(),
    numeric_level: Number(draft.numeric_level),
    shift: draft.shift || null,
    medium: draft.medium || null,
  };
}

function toDraft(c: SchoolClass): Draft {
  return {
    name: c.name,
    numeric_level: String(c.numeric_level),
    shift: c.shift ?? "",
    medium: c.medium ?? "",
  };
}

/** Shift + medium selects, shared by the create form and the inline editor. */
function ShiftMediumFields({
  draft,
  onChange,
}: {
  draft: Draft;
  onChange: (draft: Draft) => void;
}) {
  return (
    <>
      <select
        aria-label="Shift"
        value={draft.shift}
        onChange={(e) =>
          onChange({ ...draft, shift: e.target.value as Shift | "" })
        }
        className={inputClass}
      >
        <option value="">No shift</option>
        {SHIFTS.map((s) => (
          <option key={s.value} value={s.value}>
            {s.label}
          </option>
        ))}
      </select>
      <select
        aria-label="Medium"
        value={draft.medium}
        onChange={(e) =>
          onChange({ ...draft, medium: e.target.value as Medium | "" })
        }
        className={inputClass}
      >
        <option value="">No medium</option>
        {MEDIUMS.map((m) => (
          <option key={m.value} value={m.value}>
            {m.label}
          </option>
        ))}
      </select>
    </>
  );
}

export function ClassesManager() {
  const { can } = useAuth();
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [form, setForm] = useState<Draft>(EMPTY);
  const [editing, setEditing] = useState<(Draft & { id: number }) | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const canCreate = can("class.create");
  const canEdit = can("class.edit");
  const canDelete = can("class.delete");
  const canViewSections = can("section.view");
  const canViewSubjects = can("subject.view");

  const applyPage = useCallback((res: Paginated<SchoolClass>) => {
    setClasses(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  const load = useCallback(
    async (target: number) => {
      try {
        applyPage(await classService.listClasses(target));
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    classService
      .listClasses(1)
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
      await run(() => classService.createClass(input), `${input.name} created.`)
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
        () => classService.updateClass(id, toInput(draft)),
        "Class updated.",
      )
    ) {
      setEditing(null);
      await load(page);
    }
  }

  async function handleDelete(c: SchoolClass) {
    if (!window.confirm(`Delete ${c.name}? This cannot be undone.`)) return;
    if (await run(() => classService.deleteClass(c.id), `${c.name} deleted.`)) {
      await load(page);
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-4xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Classes</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        The class catalogue, shared across academic years. Level sets the order
        and drives promotion (Class 1 = 1; use 0 or below for pre-primary).
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
          className="mb-6 grid gap-2 rounded-lg border border-gray-200 p-4 sm:grid-cols-[1fr_6rem_auto_auto_auto] sm:items-end dark:border-gray-800"
        >
          <label className="text-sm">
            <span className="mb-1 block font-medium">Name</span>
            <input
              required
              maxLength={50}
              placeholder="e.g. Class 6"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className={`w-full ${inputClass}`}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Level</span>
            <input
              type="number"
              required
              min={-5}
              max={20}
              step={1}
              value={form.numeric_level}
              onChange={(e) =>
                setForm({ ...form, numeric_level: e.target.value })
              }
              className={`w-full ${inputClass}`}
            />
          </label>
          <ShiftMediumFields draft={form} onChange={setForm} />
          <button
            type="submit"
            disabled={busy}
            className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
          >
            Add class
          </button>
        </form>
      )}

      {classes.length === 0 ? (
        <p className="text-sm text-gray-500">No classes yet.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th className="py-2 pr-3 font-medium">Level</th>
                <th className="py-2 pr-3 font-medium">Name</th>
                <th className="py-2 pr-3 font-medium">Shift</th>
                <th className="py-2 pr-3 font-medium">Medium</th>
                {canViewSections && (
                  <th className="py-2 pr-3 font-medium">Sections</th>
                )}
                {canViewSubjects && (
                  <th className="py-2 pr-3 font-medium">Subjects</th>
                )}
                <th className="py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {classes.map((c) =>
                editing?.id === c.id ? (
                  <tr
                    key={c.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="py-2 pr-2">
                      <input
                        type="number"
                        aria-label="Level"
                        min={-5}
                        max={20}
                        value={editing.numeric_level}
                        onChange={(e) =>
                          setEditing({
                            ...editing,
                            numeric_level: e.target.value,
                          })
                        }
                        className={`w-16 ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-2">
                      <input
                        aria-label="Name"
                        maxLength={50}
                        value={editing.name}
                        onChange={(e) =>
                          setEditing({ ...editing, name: e.target.value })
                        }
                        className={`w-full ${inputClass}`}
                      />
                    </td>
                    <td
                      className="space-x-2 py-2 pr-2 whitespace-nowrap"
                      colSpan={2}
                    >
                      <ShiftMediumFields
                        draft={editing}
                        onChange={(draft) =>
                          setEditing({ ...draft, id: editing.id })
                        }
                      />
                    </td>
                    {canViewSections && (
                      <td className="py-2 pr-3 tabular-nums">
                        {c.sections_count ?? "—"}
                      </td>
                    )}
                    {canViewSubjects && (
                      <td className="py-2 pr-3 tabular-nums">
                        {c.subjects_count ?? "—"}
                      </td>
                    )}
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
                    key={c.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="py-2 pr-3 tabular-nums">
                      {c.numeric_level}
                    </td>
                    <td className="py-2 pr-3 font-medium">{c.name}</td>
                    <td className="py-2 pr-3">{labelOf(SHIFTS, c.shift)}</td>
                    <td className="py-2 pr-3">{labelOf(MEDIUMS, c.medium)}</td>
                    {canViewSections && (
                      <td className="py-2 pr-3">
                        <Link
                          href={`/admin/classes/${c.id}/sections`}
                          className="text-xs hover:underline"
                        >
                          {c.sections_count === 1
                            ? "1 section"
                            : `${c.sections_count ?? 0} sections`}
                        </Link>
                      </td>
                    )}
                    {canViewSubjects && (
                      <td className="py-2 pr-3">
                        <Link
                          href={`/admin/classes/${c.id}/subjects`}
                          className="text-xs hover:underline"
                        >
                          {c.subjects_count === 1
                            ? "1 subject"
                            : `${c.subjects_count ?? 0} subjects`}
                        </Link>
                      </td>
                    )}
                    <td className="space-x-3 py-2 text-right whitespace-nowrap">
                      {canEdit && (
                        <button
                          type="button"
                          onClick={() =>
                            setEditing({ ...toDraft(c), id: c.id })
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
                          onClick={() => handleDelete(c)}
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
