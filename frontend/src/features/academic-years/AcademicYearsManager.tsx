"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { formatDay } from "@/lib/format";
import * as yearService from "@/services/academicYearService";
import { errorMessage } from "@/lib/errors";
import type { AcademicYear, AcademicYearInput } from "@/types/academicYear";
import type { Paginated } from "@/types/api";
import { PageLoader } from "@/components/Loading";

const EMPTY: AcademicYearInput = { name: "", start_date: "", end_date: "" };

const inputClass = "input input-sm";

/** Client-side hint mirroring the server rule (the server stays authoritative). */
function rangeError(input: AcademicYearInput): string | null {
  if (
    input.start_date &&
    input.end_date &&
    input.end_date <= input.start_date
  ) {
    return "The end date must be after the start date.";
  }
  return null;
}

export function AcademicYearsManager() {
  const { can } = useAuth();
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [form, setForm] = useState<AcademicYearInput>(EMPTY);
  const [editing, setEditing] = useState<
    (AcademicYearInput & { id: number }) | null
  >(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const canCreate = can("academic_year.create");
  const canEdit = can("academic_year.edit");
  const canDelete = can("academic_year.delete");

  const applyPage = useCallback((res: Paginated<AcademicYear>) => {
    setYears(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  /** Reload a page after a mutation (called from event handlers). */
  const load = useCallback(
    async (target: number) => {
      try {
        applyPage(await yearService.listAcademicYears(target));
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage],
  );

  // Initial fetch: state is set only in the promise callbacks, and ignored
  // if the component unmounted in the meantime.
  useEffect(() => {
    let active = true;
    yearService
      .listAcademicYears(1)
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

  /** Run a mutation with shared busy/error/notice handling. */
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
    const invalid = rangeError(form);
    if (invalid) return setError(invalid);
    const ok = await run(
      () => yearService.createAcademicYear(form),
      `Academic year ${form.name} created.`,
    );
    if (ok) {
      setForm(EMPTY);
      await load(1);
    }
  }

  async function handleSaveEdit() {
    if (!editing) return;
    const invalid = rangeError(editing);
    if (invalid) return setError(invalid);
    const { id, ...input } = editing;
    const ok = await run(
      () => yearService.updateAcademicYear(id, input),
      "Academic year updated.",
    );
    if (ok) {
      setEditing(null);
      await load(page);
    }
  }

  async function handleSetCurrent(year: AcademicYear) {
    if (
      !window.confirm(
        `Make ${year.name} the current academic year? Pages across the system default to the current year.`,
      )
    )
      return;
    // Reload the page: the previously current year changes too.
    if (
      await run(
        () => yearService.setCurrentAcademicYear(year.id),
        `${year.name} is now the current academic year.`,
      )
    ) {
      await load(page);
    }
  }

  async function handleDelete(year: AcademicYear) {
    if (
      !window.confirm(
        `Delete academic year ${year.name}? This cannot be undone.`,
      )
    )
      return;
    if (
      await run(
        () => yearService.deleteAcademicYear(year.id),
        `Academic year ${year.name} deleted.`,
      )
    ) {
      await load(page);
    }
  }

  if (loading) {
    return <PageLoader />;
  }

  return (
    <div className="mx-auto max-w-4xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Academic years</h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Exactly one year is current; the system defaults to it everywhere.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success mb-4">{notice}</p>}

      {canCreate && (
        <form
          onSubmit={handleCreate}
          className="card mb-6 grid gap-2 p-5 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end"
        >
          <label className="text-sm">
            <span className="mb-1 block font-medium">Name</span>
            <input
              required
              maxLength={50}
              placeholder="e.g. 2027"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className={`w-full ${inputClass}`}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Starts</span>
            <input
              type="date"
              required
              value={form.start_date}
              onChange={(e) => setForm({ ...form, start_date: e.target.value })}
              className={inputClass}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Ends</span>
            <input
              type="date"
              required
              value={form.end_date}
              onChange={(e) => setForm({ ...form, end_date: e.target.value })}
              className={inputClass}
            />
          </label>
          <button type="submit" disabled={busy} className="btn btn-primary">
            Add year
          </button>
        </form>
      )}

      {years.length === 0 ? (
        <p className="text-sm text-slate-500">
          No academic years yet.
          {canCreate ? " The first one you add becomes current." : ""}
        </p>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr className="table-head">
                <th className="py-2 font-medium">Name</th>
                <th className="py-2 font-medium">Starts</th>
                <th className="py-2 font-medium">Ends</th>
                <th className="py-2 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {years.map((year) =>
                editing?.id === year.id ? (
                  <tr key={year.id} className="table-row">
                    <td className="py-2 pr-2">
                      <input
                        aria-label="Name"
                        value={editing.name}
                        maxLength={50}
                        onChange={(e) =>
                          setEditing({ ...editing, name: e.target.value })
                        }
                        className={`w-full ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-2">
                      <input
                        type="date"
                        aria-label="Starts"
                        value={editing.start_date}
                        onChange={(e) =>
                          setEditing({ ...editing, start_date: e.target.value })
                        }
                        className={inputClass}
                      />
                    </td>
                    <td className="py-2 pr-2">
                      <input
                        type="date"
                        aria-label="Ends"
                        value={editing.end_date}
                        onChange={(e) =>
                          setEditing({ ...editing, end_date: e.target.value })
                        }
                        className={inputClass}
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
                        className="text-xs text-slate-500 hover:underline"
                      >
                        Cancel
                      </button>
                    </td>
                  </tr>
                ) : (
                  <tr key={year.id} className="table-row">
                    <td className="py-2">
                      <span className="font-medium">{year.name}</span>
                      {year.is_current && (
                        <span className="ml-2 rounded bg-slate-900 px-1.5 py-0.5 text-xs text-white dark:bg-slate-100 dark:text-slate-900">
                          Current
                        </span>
                      )}
                    </td>
                    <td className="py-2 whitespace-nowrap">
                      {formatDay(year.start_date)}
                    </td>
                    <td className="py-2 whitespace-nowrap">
                      {formatDay(year.end_date)}
                    </td>
                    <td className="space-x-3 py-2 text-right whitespace-nowrap">
                      {canEdit && !year.is_current && (
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => handleSetCurrent(year)}
                          className="text-xs font-medium hover:underline"
                        >
                          Set as current
                        </button>
                      )}
                      {canEdit && (
                        <button
                          type="button"
                          onClick={() =>
                            setEditing({
                              id: year.id,
                              name: year.name,
                              start_date: year.start_date,
                              end_date: year.end_date,
                            })
                          }
                          className="text-xs text-slate-600 hover:underline dark:text-slate-300"
                        >
                          Edit
                        </button>
                      )}
                      {canDelete && !year.is_current && (
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => handleDelete(year)}
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
