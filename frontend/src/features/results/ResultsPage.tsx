"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { InlineLoader, PageLoader } from "@/components/Loading";
import { useAuth } from "@/features/auth/AuthContext";
import { examDates } from "@/features/exams/ExamsList";
import { defaultExam } from "@/features/marks/MarksPage";
import { errorMessage } from "@/lib/errors";
import { formatDate } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as examService from "@/services/examService";
import * as resultService from "@/services/resultService";
import type { Exam } from "@/types/exam";
import {
  RESULT_STATUSES,
  type ResultClassOverview,
  type ResultStatus,
  type ResultStep,
  type ResultsOverview,
} from "@/types/result";

const inputClass = "input input-sm";

export const RESULT_BADGE: Record<ResultStatus, string> = {
  draft: "badge badge-gray",
  review: "badge badge-amber",
  approved: "badge badge-brand",
  published: "badge badge-green",
};

type Action = "calculate" | ResultStep;

/** A workflow button: when it shows, who may press it, and its wording. */
const ACTIONS: {
  action: Action;
  label: string;
  permission: string;
  when: (c: ResultClassOverview) => boolean;
  primary?: boolean;
  confirm?: (c: ResultClassOverview) => string;
}[] = [
  {
    action: "calculate",
    label: "Calculate",
    permission: "result.process",
    when: (c) => c.status === null && c.sheets.total > 0,
    primary: true,
  },
  {
    action: "calculate",
    label: "Recalculate",
    permission: "result.process",
    when: (c) => c.status === "draft",
  },
  {
    action: "review",
    label: "Send for review",
    permission: "result.process",
    when: (c) => c.status === "draft",
    primary: true,
  },
  {
    action: "approve",
    label: "Approve",
    permission: "result.publish",
    when: (c) => c.status === "review",
    primary: true,
  },
  {
    action: "publish",
    label: "Publish",
    permission: "result.publish",
    when: (c) => c.status === "approved",
    primary: true,
    confirm: (c) =>
      `Publish ${c.class.name}'s results? Students and parents will see them right away.`,
  },
  {
    action: "return",
    label: "Return to draft",
    permission: "result.publish",
    when: (c) => c.status === "review" || c.status === "approved",
  },
  {
    action: "unpublish",
    label: "Unpublish",
    permission: "result.publish",
    when: (c) => c.status === "published",
    confirm: (c) =>
      `Unpublish ${c.class.name}'s results? They'll disappear from student and parent portals until published again.`,
  },
];

const DONE: Record<Action, string> = {
  calculate: "Results calculated as drafts.",
  review: "Sent for review.",
  approve: "Approved.",
  publish: "Published: students and parents can see the results now.",
  return: "Returned to draft.",
  unpublish: "Unpublished.",
};

/**
 * Results for an exam, class by class: how many mark sheets are submitted,
 * then calculate → review → approve → publish. Buttons follow permissions.
 */
export function ResultsPage({
  basePath,
  initialExamId,
}: {
  basePath: string;
  initialExamId?: number;
}) {
  const { can } = useAuth();
  const [exams, setExams] = useState<Exam[] | null>(null);
  const [examId, setExamId] = useState("");
  const [overview, setOverview] = useState<ResultsOverview | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

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
    resultService
      .getOverview(Number(examId))
      .then((o) => {
        if (active) setOverview(o);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [examId]);

  async function run(c: ResultClassOverview, action: Action, prompt?: string) {
    if (prompt && !window.confirm(prompt)) return;
    setBusy(`${c.class.id}:${action}`);
    setError(null);
    setNotice(null);
    try {
      setOverview(
        action === "calculate"
          ? await resultService.calculate(Number(examId), c.class.id)
          : await resultService.step(Number(examId), c.class.id, action),
      );
      setNotice(`${c.class.name}: ${DONE[action]}`);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(null);
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
  const loading = !overview || String(overview.exam.id) !== examId;

  return (
    <div className="mx-auto max-w-6xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Results</h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Once every mark sheet of a class is submitted, calculate its results,
        send them for review, approve, then publish. Only published results
        reach students and parents.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success mb-4">{notice}</p>}

      {exams.length === 0 ? (
        <p className="text-sm text-slate-500">No exams have been set up yet.</p>
      ) : (
        <>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <select
              aria-label="Exam"
              value={examId}
              onChange={(e) => {
                setExamId(e.target.value);
                setNotice(null);
              }}
              className={inputClass}
            >
              {exams.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.name}
                  {e.academic_year ? ` (${e.academic_year.name})` : ""}
                </option>
              ))}
            </select>
            {exam && (
              <span className="text-sm text-slate-500 dark:text-slate-400">
                {examDates(exam)}
              </span>
            )}
          </div>

          {loading ? (
            <InlineLoader />
          ) : overview.data.length === 0 ? (
            <p className="text-sm text-slate-500">
              No classes sit this exam yet.
            </p>
          ) : (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr className="table-head">
                    <th className="py-2 pr-3 font-medium">Class</th>
                    <th className="py-2 pr-3 font-medium">Marks submitted</th>
                    <th className="py-2 pr-3 font-medium">Results</th>
                    <th className="py-2 pr-3 font-medium">Passed</th>
                    <th className="py-2 font-medium">
                      <span className="sr-only">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {overview.data.map((c) => {
                    const ready = c.sheets.submitted === c.sheets.total;
                    return (
                      <tr key={c.class.id} className="table-row align-top">
                        <td className="py-2.5 pr-3 font-medium whitespace-nowrap">
                          {c.class.name}
                        </td>
                        <td className="py-2.5 pr-3">
                          <span
                            className={`text-sm tabular-nums ${ready ? "text-emerald-700 dark:text-emerald-400" : ""}`}
                          >
                            {c.sheets.submitted}/{c.sheets.total}
                          </span>
                          {!ready && c.pending.length > 0 && (
                            <span className="block max-w-56 text-xs text-slate-500">
                              Waiting for {c.pending.join(", ")}
                              {c.sheets.total - c.sheets.submitted >
                              c.pending.length
                                ? " …"
                                : ""}
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 pr-3">
                          {c.status ? (
                            <span className={RESULT_BADGE[c.status]}>
                              {labelOf(RESULT_STATUSES, c.status)}
                            </span>
                          ) : (
                            <span className="text-xs text-slate-500">
                              Not calculated
                            </span>
                          )}
                          {c.published_at ? (
                            <span className="block text-xs text-slate-500">
                              {formatDate(c.published_at)}
                            </span>
                          ) : c.calculated_at ? (
                            <span className="block text-xs text-slate-500">
                              Calculated {formatDate(c.calculated_at)}
                            </span>
                          ) : null}
                          {c.stale && c.status !== "published" && (
                            <span className="mt-1 block max-w-56 text-xs font-medium text-amber-700 dark:text-amber-400">
                              The grade scale changed since: return to draft and
                              recalculate.
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 pr-3 tabular-nums">
                          {c.status ? `${c.passed}/${c.students}` : "—"}
                        </td>
                        <td className="py-2.5">
                          <div className="flex flex-wrap justify-end gap-2">
                            {ACTIONS.filter(
                              (a) => a.when(c) && can(a.permission),
                            ).map((a) => (
                              <button
                                key={a.label}
                                type="button"
                                disabled={
                                  busy !== null ||
                                  (a.action === "calculate" && !ready)
                                }
                                title={
                                  a.action === "calculate" && !ready
                                    ? "Every mark sheet must be submitted first"
                                    : undefined
                                }
                                onClick={() =>
                                  void run(c, a.action, a.confirm?.(c))
                                }
                                className={`btn btn-sm ${a.primary ? "btn-primary" : "btn-secondary"}`}
                              >
                                {busy === `${c.class.id}:${a.action}`
                                  ? "Working…"
                                  : a.label}
                              </button>
                            ))}
                            {c.status && (
                              <Link
                                href={`${basePath}/class?exam=${examId}&class=${c.class.id}`}
                                className="btn btn-secondary btn-sm"
                              >
                                View
                              </Link>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </div>
  );
}
