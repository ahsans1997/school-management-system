"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { InlineLoader, PageLoader } from "@/components/Loading";
import { Pagination } from "@/components/Pagination";
import { examDates } from "@/features/exams/ExamsList";
import { errorMessage } from "@/lib/errors";
import { formatDay, localToday } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as examService from "@/services/examService";
import * as marksService from "@/services/marksService";
import type { Exam } from "@/types/exam";
import {
  SHEET_STATUSES,
  type MarkSheetPage,
  type MarkSheetStatus,
  type MarkSheetSummary,
} from "@/types/marks";

const inputClass = "input input-sm";

export const STATUS_BADGE: Record<MarkSheetStatus, string> = {
  not_started: "badge badge-gray",
  draft: "badge badge-amber",
  submitted: "badge badge-green",
};

export function sheetHref(
  basePath: string,
  examId: number,
  paperId: number,
  sectionId: number,
) {
  return `${basePath}/sheet?exam=${examId}&paper=${paperId}&section=${sectionId}`;
}

/** The exam to open first: the latest one that has started, else the newest. */
export function defaultExam(exams: Exam[], wanted?: number): Exam | undefined {
  const today = localToday();
  return (
    exams.find((e) => e.id === wanted) ??
    exams.find((e) => e.start_date <= today) ??
    exams[0]
  );
}

/**
 * Marks entry, step 1: choose an exam, then a sheet (one subject as sat by
 * one section). Teachers only see the subjects they teach.
 */
export function MarksPage({
  basePath,
  initialExamId,
}: {
  basePath: string;
  initialExamId?: number;
}) {
  const [exams, setExams] = useState<Exam[] | null>(null);
  const [examId, setExamId] = useState("");
  const [classId, setClassId] = useState("");
  const [sheets, setSheets] = useState<MarkSheetPage | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    examService
      .listExams()
      .then((page) => {
        if (!active) return;
        setExams(page.data);
        const first = defaultExam(page.data, initialExamId);
        if (first) setExamId(String(first.id));
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [initialExamId]);

  useEffect(() => {
    if (!examId) return;
    let active = true;
    marksService
      .listSheets(Number(examId))
      .then((page) => {
        if (!active) return;
        setError(null);
        setClassId("");
        setSheets(page);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [examId]);

  async function load(page: number, nextClassId: string) {
    setError(null);
    try {
      setSheets(
        await marksService.listSheets(Number(examId), {
          page,
          classId: nextClassId ? Number(nextClassId) : null,
        }),
      );
      setClassId(nextClassId);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  if (exams === null) {
    return error ? (
      <div className="p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const exam = exams.find((e) => String(e.id) === examId);
  const loading = !sheets || String(sheets.exam.id) !== examId;

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Marks entry</h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Enter marks per subject and section once the paper is sat. Marks stay as
        drafts until you submit them; submitted marks go on to results.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}

      {exams.length === 0 ? (
        <p className="text-sm text-slate-500">No exams have been set up yet.</p>
      ) : (
        <>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <select
              aria-label="Exam"
              value={examId}
              onChange={(e) => setExamId(e.target.value)}
              className={inputClass}
            >
              {exams.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.name}
                  {e.academic_year ? ` (${e.academic_year.name})` : ""}
                </option>
              ))}
            </select>
            {!loading && sheets.classes.length > 1 && (
              <select
                aria-label="Class"
                value={classId}
                onChange={(e) => void load(1, e.target.value)}
                className={inputClass}
              >
                <option value="">All classes</option>
                {sheets.classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            )}
            {exam && (
              <span className="text-sm text-slate-500 dark:text-slate-400">
                {examDates(exam)}
              </span>
            )}
          </div>

          {loading ? (
            <InlineLoader />
          ) : sheets.data.length === 0 ? (
            <p className="text-sm text-slate-500">
              {sheets.classes.length === 0
                ? "Nothing to enter for this exam: no papers are set for the subjects you teach."
                : "No sheets match."}
            </p>
          ) : (
            <>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr className="table-head">
                      <th className="py-2 pr-3 font-medium">Class</th>
                      <th className="py-2 pr-3 font-medium">Subject</th>
                      <th className="py-2 pr-3 font-medium">Paper date</th>
                      <th className="py-2 pr-3 font-medium">Entered</th>
                      <th className="py-2 pr-3 font-medium">Status</th>
                      <th className="py-2 font-medium">
                        <span className="sr-only">Open</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {sheets.data.map((s) => (
                      <SheetRow
                        key={`${s.exam_subject_id}-${s.section.id}`}
                        sheet={s}
                        href={sheetHref(
                          basePath,
                          sheets.exam.id,
                          s.exam_subject_id,
                          s.section.id,
                        )}
                      />
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination
                page={sheets.meta.current_page}
                lastPage={sheets.meta.last_page}
                onPage={(p) => void load(p, classId)}
              />
            </>
          )}
        </>
      )}
    </div>
  );
}

function SheetRow({ sheet, href }: { sheet: MarkSheetSummary; href: string }) {
  const done = sheet.students === 0 ? 0 : sheet.entered / sheet.students;
  return (
    <tr className="table-row">
      <td className="py-2 pr-3 font-medium whitespace-nowrap">
        {sheet.class.name} {sheet.section.name}
      </td>
      <td className="py-2 pr-3">
        {sheet.subject.name}
        <span className="block text-xs text-slate-500 tabular-nums">
          Full marks {sheet.full_marks}
        </span>
      </td>
      <td className="py-2 pr-3 whitespace-nowrap">
        {formatDay(sheet.exam_date)}
      </td>
      <td className="py-2 pr-3">
        <span className="text-xs text-slate-600 tabular-nums dark:text-slate-300">
          {sheet.entered}/{sheet.students}
        </span>
        <div className="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
          <div
            className={`h-full rounded-full ${sheet.status === "submitted" ? "bg-emerald-500" : "bg-brand-500"}`}
            style={{ width: `${Math.round(Math.min(1, done) * 100)}%` }}
          />
        </div>
      </td>
      <td className="py-2 pr-3">
        <span className={STATUS_BADGE[sheet.status]}>
          {labelOf(SHEET_STATUSES, sheet.status)}
        </span>
      </td>
      <td className="py-2 text-right whitespace-nowrap">
        {sheet.status === "submitted" ? (
          <Link href={href} className="btn btn-secondary btn-sm">
            View
          </Link>
        ) : sheet.open ? (
          <Link href={href} className="btn btn-primary btn-sm">
            Enter marks
          </Link>
        ) : (
          <Link
            href={href}
            className="text-xs text-slate-500 hover:underline"
            title="Marks open once the paper is sat"
          >
            Opens {formatDay(sheet.opens_on)}
          </Link>
        )}
      </td>
    </tr>
  );
}
