"use client";

import Link from "next/link";
import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import { ApiRequestError } from "@/services/apiClient";
import * as subjectService from "@/services/subjectService";
import type { SchoolClass } from "@/types/schoolClass";
import {
  SUBJECT_TYPES,
  type ClassSubject,
  type ClassSubjectPage,
  type Subject,
} from "@/types/subject";
import { PageLoader } from "@/components/Loading";

/** The catalogue is loaded in one request for the picker (the API max). */
const CATALOGUE_SIZE = 100;

const inputClass = "input input-sm";

const isNotFound = (err: unknown) =>
  err instanceof ApiRequestError && err.error.status === 404;

/** Subjects taught in one class: list, assign from the catalogue, unassign. */
export function ClassSubjectsManager({ classId }: { classId: number }) {
  const { can } = useAuth();
  const canAssign = can("subject.edit");
  const [schoolClass, setSchoolClass] = useState<SchoolClass | null>(null);
  const [assignments, setAssignments] = useState<ClassSubject[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [catalogue, setCatalogue] = useState<Subject[]>([]);
  const [catalogueTotal, setCatalogueTotal] = useState(0);
  const [selected, setSelected] = useState("");
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const applyPage = useCallback((res: ClassSubjectPage) => {
    setSchoolClass(res.class);
    setAssignments(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  const load = useCallback(
    async (target: number) => {
      try {
        applyPage(await subjectService.listClassSubjects(classId, target));
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage, classId],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    Promise.all([
      subjectService.listClassSubjects(classId, 1),
      canAssign ? subjectService.listSubjects(1, CATALOGUE_SIZE) : null,
    ])
      .then(([assigned, all]) => {
        if (!active) return;
        applyPage(assigned);
        if (all) {
          setCatalogue(all.data);
          setCatalogueTotal(all.meta.total);
        }
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
  }, [applyPage, canAssign, classId]);

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

  async function handleAssign(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const subject = catalogue.find((s) => s.id === Number(selected));
    if (!subject) return;
    if (
      await run(
        () => subjectService.assignSubject(classId, subject.id),
        `${subject.name} assigned.`,
      )
    ) {
      setSelected("");
      await load(page);
    }
  }

  async function handleUnassign(a: ClassSubject) {
    const where = schoolClass ? ` from ${schoolClass.name}` : "";
    if (!window.confirm(`Remove ${a.subject.name}${where}?`)) return;
    if (
      await run(
        () => subjectService.unassignSubject(classId, a.subject_id),
        `${a.subject.name} removed.`,
      )
    ) {
      // Step back if that emptied the last page.
      await load(assignments.length === 1 && page > 1 ? page - 1 : page);
    }
  }

  const backLink = (
    <Link
      href="/admin/classes"
      className="text-sm text-slate-500 hover:underline dark:text-slate-400"
    >
      ← Classes
    </Link>
  );

  if (loading) {
    return <PageLoader />;
  }

  if (missing) {
    return (
      <div className="mx-auto max-w-3xl p-6">
        {backLink}
        <p className="mt-4 text-sm">This class doesn&apos;t exist.</p>
      </div>
    );
  }

  // Already-assigned subjects (on this page) aren't offered again; the API
  // rejects any duplicate regardless.
  const assignedIds = new Set(assignments.map((a) => a.subject_id));
  const options = catalogue.filter((s) => !assignedIds.has(s.id));

  return (
    <div className="mx-auto max-w-3xl p-6">
      {backLink}
      <h1 className="mt-2 mb-1 text-2xl font-semibold">
        {schoolClass ? `${schoolClass.name} — subjects` : "Subjects"}
      </h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Subjects taught in this class, picked from the subject catalogue.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success mb-4">{notice}</p>}

      {canAssign &&
        (catalogue.length === 0 ? (
          <p className="mb-6 text-sm text-slate-500">
            The subject catalogue is empty.{" "}
            <Link href="/admin/subjects" className="underline">
              Add subjects
            </Link>{" "}
            first.
          </p>
        ) : (
          <form
            onSubmit={handleAssign}
            className="card mb-6 flex flex-wrap items-end gap-2 p-5"
          >
            <label className="min-w-48 flex-1 text-sm">
              <span className="mb-1 block font-medium">Subject</span>
              <select
                required
                value={selected}
                onChange={(e) => setSelected(e.target.value)}
                className={`w-full ${inputClass}`}
              >
                <option value="">Choose a subject…</option>
                {options.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.code ? `${s.name} (${s.code})` : s.name}
                  </option>
                ))}
              </select>
            </label>
            <button
              type="submit"
              disabled={busy || !selected}
              className="btn btn-primary"
            >
              Assign
            </button>
            {catalogueTotal > catalogue.length && (
              <p className="w-full text-xs text-slate-500">
                Showing the first {catalogue.length} of {catalogueTotal}{" "}
                subjects.
              </p>
            )}
          </form>
        ))}

      {assignments.length === 0 ? (
        <p className="text-sm text-slate-500">No subjects assigned yet.</p>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr className="table-head">
                <th className="py-2 pr-3 font-medium">Subject</th>
                <th className="py-2 pr-3 font-medium">Code</th>
                <th className="py-2 pr-3 font-medium">Type</th>
                {canAssign && (
                  <th className="py-2 text-right font-medium">Actions</th>
                )}
              </tr>
            </thead>
            <tbody>
              {assignments.map((a) => (
                <tr key={a.id} className="table-row">
                  <td className="py-2 pr-3 font-medium">{a.subject.name}</td>
                  <td className="py-2 pr-3 tabular-nums">
                    {a.subject.code ?? "—"}
                  </td>
                  <td className="py-2 pr-3">
                    {labelOf(SUBJECT_TYPES, a.subject.type)}
                  </td>
                  {canAssign && (
                    <td className="py-2 text-right whitespace-nowrap">
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => handleUnassign(a)}
                        className="text-xs text-red-600 hover:underline"
                      >
                        Remove
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Pagination page={page} lastPage={lastPage} onPage={load} />
    </div>
  );
}
