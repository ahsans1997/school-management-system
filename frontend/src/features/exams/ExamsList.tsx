"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDay } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as academicYearService from "@/services/academicYearService";
import * as examService from "@/services/examService";
import type { AcademicYear } from "@/types/academicYear";
import type { Paginated } from "@/types/api";
import {
  EXAM_TYPES,
  type Exam,
  type ExamInput,
  type ExamType,
  type GradeScale,
} from "@/types/exam";
import { ExamForm } from "./ExamForm";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

interface Filters {
  yearId: string;
  type: ExamType | "";
  archived: boolean;
}

export function examDates(exam: {
  start_date: string;
  end_date: string | null;
}) {
  return exam.end_date && exam.end_date !== exam.start_date
    ? `${formatDay(exam.start_date)} – ${formatDay(exam.end_date)}`
    : formatDay(exam.start_date);
}

/** Exams with year/type/archived filters; new exams are set up from here. */
export function ExamsList() {
  const { can } = useAuth();
  const [exams, setExams] = useState<Exam[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [scales, setScales] = useState<GradeScale[]>([]);
  const [filters, setFilters] = useState<Filters>({
    yearId: "",
    type: "",
    archived: false,
  });
  const [creating, setCreating] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const applyPage = useCallback((res: Paginated<Exam>) => {
    setExams(res.data);
    setPage(res.meta.current_page);
    setLastPage(res.meta.last_page);
  }, []);

  const load = useCallback(
    async (target: number, next: Filters) => {
      setError(null);
      try {
        applyPage(
          await examService.listExams({
            page: target,
            academicYearId: next.yearId ? Number(next.yearId) : null,
            type: next.type,
            archived: next.archived,
          }),
        );
        setFilters(next);
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [applyPage],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    Promise.all([
      examService.listExams(),
      academicYearService.listAcademicYears(1, 100),
      examService.listGradeScales(),
    ])
      .then(([page, yearPage, scaleList]) => {
        if (!active) return;
        applyPage(page);
        setYears(yearPage.data);
        setScales(scaleList);
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

  async function create(input: ExamInput) {
    const exam = await examService.createExam(input);
    setCreating(false);
    setNotice(`${exam.name} created. Open it to add each class's subjects.`);
    await load(1, { ...filters, archived: false });
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold">
          {filters.archived ? "Archived exams" : "Exams"}
        </h1>
        {can("exam.create") && !creating && (
          <button
            type="button"
            onClick={() => {
              setCreating(true);
              setNotice(null);
            }}
            className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
          >
            New exam
          </button>
        )}
      </div>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Each exam lists the subjects every class sits, with full and pass marks
        and the date. Marks entry and results build on these papers.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      {creating && (
        <section className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
          <h2 className="mb-3 text-sm font-semibold">New exam</h2>
          <ExamForm
            exam={null}
            years={years}
            scales={scales}
            submitLabel="Create exam"
            onSubmit={create}
            onCancel={() => setCreating(false)}
          />
        </section>
      )}

      <div className="mb-4 flex flex-wrap items-center gap-2">
        <select
          aria-label="Academic year"
          value={filters.yearId}
          onChange={(e) => void load(1, { ...filters, yearId: e.target.value })}
          className={inputClass}
        >
          <option value="">All years</option>
          {years.map((y) => (
            <option key={y.id} value={y.id}>
              {y.name}
            </option>
          ))}
        </select>
        <select
          aria-label="Type"
          value={filters.type}
          onChange={(e) =>
            void load(1, { ...filters, type: e.target.value as ExamType | "" })
          }
          className={inputClass}
        >
          <option value="">All types</option>
          {EXAM_TYPES.map((t) => (
            <option key={t.value} value={t.value}>
              {t.label}
            </option>
          ))}
        </select>
        <label className="ml-auto flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={filters.archived}
            onChange={(e) =>
              void load(1, { ...filters, archived: e.target.checked })
            }
          />
          Show archived
        </label>
      </div>

      {exams.length === 0 ? (
        <p className="text-sm text-gray-500">
          {filters.archived ? "No archived exams." : "No exams yet."}
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th className="py-2 pr-3 font-medium">Exam</th>
                <th className="py-2 pr-3 font-medium">Type</th>
                <th className="py-2 pr-3 font-medium">Year</th>
                <th className="py-2 pr-3 font-medium">Dates</th>
                <th className="py-2 pr-3 font-medium">Classes</th>
                <th className="py-2 pr-3 font-medium">Papers</th>
              </tr>
            </thead>
            <tbody>
              {exams.map((e) => (
                <tr
                  key={e.id}
                  className="border-b border-gray-100 dark:border-gray-900"
                >
                  <td className="py-2 pr-3">
                    <Link
                      href={`/admin/exams/${e.id}`}
                      className="font-medium hover:underline"
                    >
                      {e.name}
                    </Link>
                  </td>
                  <td className="py-2 pr-3">{labelOf(EXAM_TYPES, e.type)}</td>
                  <td className="py-2 pr-3">{e.academic_year?.name}</td>
                  <td className="py-2 pr-3 whitespace-nowrap">
                    {examDates(e)}
                  </td>
                  <td className="py-2 pr-3 tabular-nums">
                    {e.classes_count ?? 0}
                  </td>
                  <td className="py-2 pr-3 tabular-nums">
                    {e.papers_count ?? 0}
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
        onPage={(p) => void load(p, filters)}
      />
    </div>
  );
}
