"use client";

import Link from "next/link";
import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import * as sectionService from "@/services/sectionService";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section, SectionInput, SectionPage } from "@/types/section";

/** Form fields as typed by the user. */
interface Draft {
  name: string;
  capacity: string;
}

const EMPTY: Draft = { name: "", capacity: "" };

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

function toInput(draft: Draft): SectionInput {
  return { name: draft.name.trim(), capacity: Number(draft.capacity) };
}

const isNotFound = (err: unknown) =>
  err instanceof ApiRequestError && err.error.status === 404;

/** Sections of one class: list, create, inline edit, delete. */
export function SectionsManager({ classId }: { classId: number }) {
  const { can } = useAuth();
  const [schoolClass, setSchoolClass] = useState<SchoolClass | null>(null);
  const [sections, setSections] = useState<Section[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [form, setForm] = useState<Draft>(EMPTY);
  const [editing, setEditing] = useState<(Draft & { id: number }) | null>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const canCreate = can("section.create");
  const canEdit = can("section.edit");
  const canDelete = can("section.delete");

  const applyPage = useCallback((res: SectionPage) => {
    setSchoolClass(res.class);
    setSections(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  const load = useCallback(
    async (target: number) => {
      try {
        applyPage(await sectionService.listSections(classId, target));
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage, classId],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    sectionService
      .listSections(classId, 1)
      .then((res) => {
        if (active) applyPage(res);
      })
      .catch((err) => {
        if (!active) return;
        if (isNotFound(err)) setMissing(true);
        else setError(errorMessage(err));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [applyPage, classId]);

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
        () => sectionService.createSection(classId, input),
        `Section ${input.name} created.`,
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
        () => sectionService.updateSection(id, toInput(draft)),
        "Section updated.",
      )
    ) {
      setEditing(null);
      await load(page);
    }
  }

  async function handleDelete(section: Section) {
    if (
      !window.confirm(`Delete section ${section.name}? This cannot be undone.`)
    )
      return;
    if (
      await run(
        () => sectionService.deleteSection(section.id),
        `Section ${section.name} deleted.`,
      )
    ) {
      // Step back if that emptied the last page.
      await load(sections.length === 1 && page > 1 ? page - 1 : page);
    }
  }

  const backLink = (
    <Link
      href="/admin/classes"
      className="text-sm text-gray-500 hover:underline dark:text-gray-400"
    >
      ← Classes
    </Link>
  );

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  if (missing) {
    return (
      <div className="mx-auto max-w-3xl p-6">
        {backLink}
        <p className="mt-4 text-sm">This class doesn&apos;t exist.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl p-6">
      {backLink}
      <h1 className="mt-2 mb-1 text-2xl font-semibold">
        {schoolClass ? `${schoolClass.name} — sections` : "Sections"}
      </h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Capacity is the number of seats; it caps enrollment in the section.
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
          className="mb-6 grid gap-2 rounded-lg border border-gray-200 p-4 sm:grid-cols-[1fr_8rem_auto] sm:items-end dark:border-gray-800"
        >
          <label className="text-sm">
            <span className="mb-1 block font-medium">Name</span>
            <input
              required
              maxLength={50}
              placeholder="e.g. A"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className={`w-full ${inputClass}`}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Capacity</span>
            <input
              type="number"
              required
              min={1}
              max={1000}
              step={1}
              placeholder="e.g. 40"
              value={form.capacity}
              onChange={(e) => setForm({ ...form, capacity: e.target.value })}
              className={`w-full ${inputClass}`}
            />
          </label>
          <button
            type="submit"
            disabled={busy}
            className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
          >
            Add section
          </button>
        </form>
      )}

      {sections.length === 0 ? (
        <p className="text-sm text-gray-500">No sections yet.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th className="py-2 pr-3 font-medium">Section</th>
                <th className="py-2 pr-3 font-medium">Capacity</th>
                <th className="py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {sections.map((section) =>
                editing?.id === section.id ? (
                  <tr
                    key={section.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
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
                    <td className="py-2 pr-2">
                      <input
                        type="number"
                        aria-label="Capacity"
                        min={1}
                        max={1000}
                        value={editing.capacity}
                        onChange={(e) =>
                          setEditing({ ...editing, capacity: e.target.value })
                        }
                        className={`w-24 ${inputClass}`}
                      />
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
                    key={section.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="py-2 pr-3 font-medium">{section.name}</td>
                    <td className="py-2 pr-3 tabular-nums">
                      {section.capacity}
                    </td>
                    <td className="space-x-3 py-2 text-right whitespace-nowrap">
                      {canEdit && (
                        <button
                          type="button"
                          onClick={() =>
                            setEditing({
                              id: section.id,
                              name: section.name,
                              capacity: String(section.capacity),
                            })
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
                          onClick={() => handleDelete(section)}
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
