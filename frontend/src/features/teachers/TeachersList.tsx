"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import * as teacherService from "@/services/teacherService";
import type { Paginated } from "@/types/api";
import type { Teacher } from "@/types/teacher";
import { PageLoader } from "@/components/Loading";

const inputClass = "input input-sm";

/** The teacher register: search, archived toggle, links to each profile. */
export function TeachersList() {
  const { can } = useAuth();
  const [teachers, setTeachers] = useState<Teacher[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState(""); // the search the list reflects
  const [archived, setArchived] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const applyPage = useCallback((res: Paginated<Teacher>) => {
    setTeachers(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
    setTotal(res.meta.total);
  }, []);

  const load = useCallback(
    async (target: number, term: string, showArchived: boolean) => {
      setError(null);
      try {
        applyPage(
          await teacherService.listTeachers({
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
    teacherService
      .listTeachers()
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
          {archived ? "Archived teachers" : "Teachers"}
        </h1>
        {can("teacher.create") && (
          <Link href="/admin/teachers/new" className="btn btn-primary">
            Add teacher
          </Link>
        )}
      </div>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {archived
          ? "Teachers who have left. Their records are kept and can be restored."
          : "Assign subjects and class-teacher duties from each teacher's profile."}
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}

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
          aria-label="Search teachers"
          placeholder="Name, employee ID, phone or department"
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

      {teachers.length === 0 ? (
        <p className="text-sm text-slate-500">
          {query
            ? `No teachers match “${query}”.`
            : archived
              ? "No archived teachers."
              : "No teachers yet."}
        </p>
      ) : (
        <>
          <p className="mb-2 text-xs text-slate-500">
            {total} {total === 1 ? "teacher" : "teachers"}
          </p>
          <div className="table-wrap">
            <table>
              <thead>
                <tr className="table-head">
                  <th className="py-2 pr-3 font-medium">Teacher</th>
                  <th className="py-2 pr-3 font-medium">Employee ID</th>
                  <th className="py-2 pr-3 font-medium">Designation</th>
                  <th className="py-2 pr-3 font-medium">Department</th>
                  <th className="py-2 pr-3 font-medium">Teaches</th>
                  <th className="py-2 pr-3 font-medium">Login</th>
                </tr>
              </thead>
              <tbody>
                {teachers.map((t) => (
                  <tr key={t.id} className="table-row">
                    <td className="py-2 pr-3">
                      <Link
                        href={`/admin/teachers/${t.id}`}
                        className="flex items-center gap-2 font-medium hover:underline"
                      >
                        {t.photo_url ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img
                            src={t.photo_url}
                            alt=""
                            className="h-8 w-8 shrink-0 rounded-full object-cover"
                          />
                        ) : (
                          <span
                            aria-hidden
                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                          >
                            {t.name.charAt(0)}
                          </span>
                        )}
                        {t.name}
                      </Link>
                    </td>
                    <td className="py-2 pr-3 whitespace-nowrap tabular-nums">
                      {t.employee_id}
                    </td>
                    <td className="py-2 pr-3">{t.designation ?? "—"}</td>
                    <td className="py-2 pr-3">{t.department ?? "—"}</td>
                    <td className="py-2 pr-3 tabular-nums">
                      {t.assignments_count === 1
                        ? "1 subject"
                        : `${t.assignments_count ?? 0} subjects`}
                    </td>
                    <td className="py-2 pr-3">
                      {t.account ? (
                        t.account.is_active ? (
                          "Yes"
                        ) : (
                          <span className="text-red-600">Disabled</span>
                        )
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
