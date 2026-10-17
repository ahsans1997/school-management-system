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

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

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
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold">
          {archived ? "Archived students" : "Students"}
        </h1>
        <div className="flex flex-wrap gap-2 text-sm">
          <Link
            href="/admin/id-cards"
            className="rounded-md border border-gray-300 px-3 py-1.5 dark:border-gray-700"
          >
            ID cards
          </Link>
          {can("student.export") && (
            <button
              type="button"
              aria-expanded={showExport}
              onClick={() => setShowExport((v) => !v)}
              className="rounded-md border border-gray-300 px-3 py-1.5 dark:border-gray-700"
            >
              Export
            </button>
          )}
          {can("student.create") && (
            <>
              <Link
                href="/admin/students/import"
                className="rounded-md border border-gray-300 px-3 py-1.5 dark:border-gray-700"
              >
                Import
              </Link>
              <Link
                href="/admin/students/new"
                className="rounded-md bg-gray-900 px-4 py-1.5 text-white dark:bg-gray-100 dark:text-gray-900"
              >
                Add student
              </Link>
            </>
          )}
        </div>
      </div>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        {archived
          ? "Archived records are kept and can be restored."
          : "Class, section and roll are for the current academic year."}
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
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
        <button
          type="submit"
          className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
        >
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
        <p className="text-sm text-gray-500">
          {query
            ? `No students match “${query}”.`
            : archived
              ? "No archived students."
              : "No students yet."}
        </p>
      ) : (
        <>
          <p className="mb-2 text-xs text-gray-500">
            {total} {total === 1 ? "student" : "students"}
          </p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
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
                  <tr
                    key={s.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
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
                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300"
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
                          <span className="block text-xs text-gray-500">
                            Roll {s.current_enrollment.roll}
                            {s.current_enrollment.status !== "active" &&
                              ` · ${s.current_enrollment.status}`}
                          </span>
                        </>
                      ) : (
                        <span className="text-gray-500">Not enrolled</span>
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
                          <span className="block text-xs text-gray-500 tabular-nums">
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
