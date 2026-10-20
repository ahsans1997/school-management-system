"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as studentService from "@/services/studentService";
import type { Paginated } from "@/types/api";
import { GENDERS, type Student } from "@/types/student";
import { StudentExportPanel } from "./StudentExportPanel";
import { PageLoader } from "@/components/Loading";

const inputClass = "input input-sm";

/** The student register: search, archived toggle, links to each profile. */
export function StudentsList() {
  const { can } = useAuth();
  const [students, setStudents] = useState<Student[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState(""); // the search the list reflects
  const [archived, setArchived] = useState(false);
  const [showExport, setShowExport] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const applyPage = useCallback((res: Paginated<Student>) => {
    setStudents(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
    setTotal(res.meta.total);
  }, []);

  const load = useCallback(
    async (target: number, term: string, showArchived: boolean) => {
      setError(null);
      try {
        applyPage(
          await studentService.listStudents({
            page: target,
            search: term,
            archived: showArchived,
          }),
        );
        setQuery(term);
        setArchived(showArchived);
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    studentService
      .listStudents()
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

  if (loading) {
    return <PageLoader />;
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold">
          {archived ? "Archived students" : "Students"}
        </h1>
        <div className="flex flex-wrap gap-2 text-sm">
          <Link href="/admin/id-cards" className="btn btn-secondary">
            ID cards
          </Link>
          {can("student.export") && (
            <button
              type="button"
              aria-expanded={showExport}
              onClick={() => setShowExport((v) => !v)}
              className="btn btn-secondary"
            >
              Export
            </button>
          )}
          {can("student.create") && (
            <>
              <Link href="/admin/students/import" className="btn btn-secondary">
                Import
              </Link>
              <Link href="/admin/students/new" className="btn btn-primary">
                Add student
              </Link>
            </>
          )}
        </div>
      </div>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {archived
          ? "Archived records are kept and can be restored."
          : "Class, section and roll are for the current academic year."}
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}

      {showExport && <StudentExportPanel search={query} archived={archived} />}

      <form
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          void load(1, search.trim(), archived);
        }}
        className="mb-4 flex flex-wrap items-center gap-2"
      >
        <input
          type="search"
          aria-label="Search students"
          placeholder="Name, student ID, admission no. or phone"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className={`w-full max-w-xs ${inputClass}`}
        />
        <button type="submit" className="btn btn-secondary">
          Search
        </button>
        <label className="ml-auto flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={archived}
            onChange={(e) => void load(1, query, e.target.checked)}
          />
          Show archived
        </label>
      </form>

      {students.length === 0 ? (
        <p className="text-sm text-slate-500">
          {query
            ? `No students match “${query}”.`
            : archived
              ? "No archived students."
              : "No students yet."}
        </p>
      ) : (
        <>
          <p className="mb-2 text-xs text-slate-500">
            {total} {total === 1 ? "student" : "students"}
          </p>
          <div className="table-wrap">
            <table>
              <thead>
                <tr className="table-head">
                  <th className="py-2 pr-3 font-medium">Student</th>
                  <th className="py-2 pr-3 font-medium">Class</th>
                  <th className="py-2 pr-3 font-medium">Student ID</th>
                  <th className="py-2 pr-3 font-medium">Admission no.</th>
                  <th className="py-2 pr-3 font-medium">Gender</th>
                  <th className="py-2 pr-3 font-medium">Primary guardian</th>
                </tr>
              </thead>
              <tbody>
                {students.map((s) => (
                  <tr key={s.id} className="table-row">
                    <td className="py-2 pr-3">
                      <Link
                        href={`/admin/students/${s.id}`}
                        className="flex items-center gap-2 font-medium hover:underline"
                      >
                        {s.photo_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img
                            src={s.photo_url}
                            alt=""
                            className="h-8 w-8 shrink-0 rounded-full object-cover"
                          />
                        ) : (
                          <span
                            aria-hidden
                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                          >
                            {s.name.charAt(0)}
                          </span>
                        )}
                        {s.name}
                      </Link>
                    </td>
                    <td className="py-2 pr-3 whitespace-nowrap">
                      {s.current_enrollment ? (
                        <>
                          {s.current_enrollment.class?.name}{" "}
                          {s.current_enrollment.section?.name}
                          <span className="block text-xs text-slate-500">
                            Roll {s.current_enrollment.roll}
                            {s.current_enrollment.status !== "active" &&
                              ` · ${s.current_enrollment.status}`}
                          </span>
                        </>
                      ) : (
                        <span className="text-slate-500">Not enrolled</span>
                      )}
                    </td>
                    <td className="py-2 pr-3 tabular-nums">{s.student_id}</td>
                    <td className="py-2 pr-3 whitespace-nowrap tabular-nums">
                      {s.admission_no}
                    </td>
                    <td className="py-2 pr-3">{labelOf(GENDERS, s.gender)}</td>
                    <td className="py-2 pr-3">
                      {s.primary_guardian ? (
                        <span>
                          {s.primary_guardian.name}
                          <span className="block text-xs text-slate-500 tabular-nums">
                            {s.primary_guardian.phone}
                          </span>
                        </span>
                      ) : (
                        "—"
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}

      <Pagination
        page={page}
        lastPage={lastPage}
        onPage={(target) => load(target, query, archived)}
      />
    </div>
  );
}
