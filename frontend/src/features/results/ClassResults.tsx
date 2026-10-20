"use client";

import { Printer } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { InlineLoader, PageLoader } from "@/components/Loading";
import { Pagination } from "@/components/Pagination";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as resultService from "@/services/resultService";
import {
  RESULT_STATUSES,
  type ResultPage,
  type ResultRow,
  type ResultStatistics,
  type Tabulation,
} from "@/types/result";
import { RESULT_BADGE } from "./ResultsPage";

const inputClass = "input input-sm";

type Tab = "merit" | "tabulation" | "statistics";

const TABS: { value: Tab; label: string }[] = [
  { value: "merit", label: "Merit list" },
  { value: "tabulation", label: "Tabulation sheet" },
  { value: "statistics", label: "Statistics" },
];

const n = (v: number | null | undefined) =>
  v === null || v === undefined ? "—" : String(v);

export function PassBadge({ row }: { row: Pick<ResultRow, "passed"> }) {
  return row.passed ? (
    <span className="badge badge-green">Passed</span>
  ) : (
    <span className="badge badge-red">Failed</span>
  );
}

/**
 * One class's results for an exam: the merit list (class or section order),
 * the printable tabulation sheet, and statistics.
 */
export function ClassResults({
  examId,
  classId,
  basePath,
}: {
  examId: number;
  classId: number;
  basePath: string;
}) {
  const [tab, setTab] = useState<Tab>("merit");
  const [sectionId, setSectionId] = useState("");
  const [stats, setStats] = useState<ResultStatistics | null>(null);
  const [page, setPage] = useState<ResultPage | null>(null);
  const [tabulation, setTabulation] = useState<Tabulation | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Which section each loaded view is for, so a stale one isn't shown.
  const [loadedFor, setLoadedFor] = useState({ list: "-", tab: "-" });

  // Header, statistics and the merit list follow the section filter.
  useEffect(() => {
    let active = true;
    const section = sectionId ? Number(sectionId) : null;
    Promise.all([
      resultService.getStatistics(examId, classId, section),
      resultService.listResults(examId, classId, { sectionId: section }),
    ])
      .then(([s, p]) => {
        if (!active) return;
        setError(null);
        setStats(s);
        setPage(p);
        setLoadedFor((l) => ({ ...l, list: sectionId }));
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [examId, classId, sectionId]);

  // The tabulation sheet loads when its tab is opened.
  useEffect(() => {
    if (tab !== "tabulation") return;
    let active = true;
    resultService
      .getTabulation(examId, classId, sectionId ? Number(sectionId) : null)
      .then((t) => {
        if (!active) return;
        setTabulation(t);
        setLoadedFor((l) => ({ ...l, tab: sectionId }));
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [tab, examId, classId, sectionId]);

  async function goTo(target: number) {
    try {
      setPage(
        await resultService.listResults(examId, classId, {
          sectionId: sectionId ? Number(sectionId) : null,
          page: target,
        }),
      );
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  if (!stats) {
    return error ? (
      <div className="mx-auto max-w-6xl p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const bySection = sectionId !== "";
  const listReady = page && loadedFor.list === sectionId;
  const tabReady = tabulation && loadedFor.tab === sectionId;

  return (
    <div className="mx-auto max-w-6xl p-6">
      <Link
        href={`${basePath}?exam=${examId}`}
        className="text-sm text-slate-500 hover:underline print:hidden"
      >
        ← Results
      </Link>
      <div className="mt-1 mb-1 flex flex-wrap items-center gap-3">
        <h1 className="text-2xl font-semibold">
          {stats.class.name}
          {stats.section ? ` ${stats.section.name}` : ""} · {stats.exam.name}
        </h1>
        {stats.status && (
          <span className={`${RESULT_BADGE[stats.status]} print:hidden`}>
            {labelOf(RESULT_STATUSES, stats.status)}
          </span>
        )}
      </div>
      <p className="mb-5 text-sm text-slate-500 dark:text-slate-400 print:hidden">
        Positions count only students who passed every subject; equal GPA and
        total marks share a position.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}

      <div className="mb-5 flex flex-wrap items-center gap-2 print:hidden">
        <div role="tablist" className="flex gap-1">
          {TABS.map((t) => (
            <button
              key={t.value}
              type="button"
              role="tab"
              aria-selected={tab === t.value}
              onClick={() => setTab(t.value)}
              className={`rounded-md px-3 py-1 text-sm ${tab === t.value ? "bg-brand-600 text-white" : "border border-slate-300 dark:border-slate-700"}`}
            >
              {t.label}
            </button>
          ))}
        </div>
        {stats.sections.length > 1 && (
          <select
            aria-label="Section"
            value={sectionId}
            onChange={(e) => setSectionId(e.target.value)}
            className={inputClass}
          >
            <option value="">Whole class</option>
            {stats.sections.map((s) => (
              <option key={s.id} value={s.id}>
                Section {s.name}
              </option>
            ))}
          </select>
        )}
        {tab === "tabulation" && (
          <button
            type="button"
            onClick={() => window.print()}
            className="btn btn-secondary btn-sm ml-auto"
          >
            <Printer className="h-4 w-4" /> Print
          </button>
        )}
      </div>

      {tab === "merit" &&
        (!listReady ? (
          <InlineLoader />
        ) : page.data.length === 0 ? (
          <p className="text-sm text-slate-500">No results to show.</p>
        ) : (
          <>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr className="table-head">
                    <th className="py-2 pr-3 font-medium">Position</th>
                    <th className="py-2 pr-3 font-medium">Roll</th>
                    <th className="py-2 pr-3 font-medium">Student</th>
                    {!bySection && (
                      <th className="py-2 pr-3 font-medium">Section</th>
                    )}
                    <th className="py-2 pr-3 font-medium">Total</th>
                    <th className="py-2 pr-3 font-medium">GPA</th>
                    <th className="py-2 pr-3 font-medium">Grade</th>
                    <th className="py-2 pr-3 font-medium">Result</th>
                    <th className="py-2 font-medium">
                      <span className="sr-only">Report card</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {page.data.map((r) => (
                    <tr key={r.id} className="table-row">
                      <td className="py-2 pr-3 font-semibold tabular-nums">
                        {n(bySection ? r.position : r.class_position)}
                      </td>
                      <td className="py-2 pr-3 text-slate-500 tabular-nums">
                        {r.roll}
                      </td>
                      <td className="py-2 pr-3 font-medium">
                        {r.student.name}
                      </td>
                      {!bySection && (
                        <td className="py-2 pr-3">{r.section.name}</td>
                      )}
                      <td className="py-2 pr-3 tabular-nums">
                        {r.total_marks}/{r.full_marks}
                        <span className="block text-xs text-slate-500">
                          {r.percentage}%
                        </span>
                      </td>
                      <td className="py-2 pr-3 font-semibold tabular-nums">
                        {r.gpa.toFixed(2)}
                      </td>
                      <td className="py-2 pr-3 font-semibold">{r.grade}</td>
                      <td className="py-2 pr-3">
                        <PassBadge row={r} />
                        {r.failed_subjects > 0 && (
                          <span className="block text-xs text-slate-500">
                            {r.failed_subjects} failed
                          </span>
                        )}
                      </td>
                      <td className="py-2 text-right">
                        <Link
                          href={`${basePath}/report-card?id=${r.id}`}
                          className="btn btn-secondary btn-sm"
                        >
                          Report card
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination
              page={page.meta.current_page}
              lastPage={page.meta.last_page}
              onPage={(p) => void goTo(p)}
            />
          </>
        ))}

      {tab === "tabulation" &&
        (!tabReady ? (
          <InlineLoader />
        ) : (
          <TabulationSheet data={tabulation} bySection={bySection} />
        ))}

      {tab === "statistics" && <Statistics stats={stats} />}
    </div>
  );
}

function TabulationSheet({
  data,
  bySection,
}: {
  data: Tabulation;
  bySection: boolean;
}) {
  if (data.rows.length === 0) {
    return <p className="text-sm text-slate-500">No results to show.</p>;
  }
  const cell = "border border-slate-200 px-2 py-1 dark:border-slate-700";
  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-xs">
        <thead>
          <tr className="bg-slate-50 dark:bg-slate-800/60">
            <th className={`${cell} text-left`}>Pos.</th>
            <th className={`${cell} text-left`}>Roll</th>
            <th className={`${cell} text-left`}>Student</th>
            {!bySection && <th className={`${cell} text-left`}>Sec.</th>}
            {data.subjects.map((s) => (
              <th key={s.exam_subject_id} className={`${cell} text-center`}>
                {s.subject}
                <span className="block font-normal text-slate-500">
                  /{s.full_marks}
                </span>
              </th>
            ))}
            <th className={`${cell} text-center`}>Total</th>
            <th className={`${cell} text-center`}>GPA</th>
            <th className={`${cell} text-center`}>Grade</th>
          </tr>
        </thead>
        <tbody>
          {data.rows.map((r) => (
            <tr key={r.id}>
              <td className={`${cell} tabular-nums`}>
                {n(bySection ? r.position : r.class_position)}
              </td>
              <td className={`${cell} tabular-nums`}>{r.roll}</td>
              <td className={`${cell} font-medium whitespace-nowrap`}>
                {r.student.name}
              </td>
              {!bySection && <td className={cell}>{r.section.name}</td>}
              {data.subjects.map((s) => {
                const c = r.subjects[String(s.exam_subject_id)];
                return (
                  <td
                    key={s.exam_subject_id}
                    className={`${cell} text-center tabular-nums ${c && !c.passed ? "text-red-600 dark:text-red-400" : ""}`}
                  >
                    {!c ? (
                      <span className="text-slate-400">·</span>
                    ) : c.absent ? (
                      "Abs"
                    ) : (
                      <>
                        {c.obtained}
                        <span className="block text-[10px] text-slate-500">
                          {c.grade}
                        </span>
                      </>
                    )}
                  </td>
                );
              })}
              <td className={`${cell} text-center font-semibold tabular-nums`}>
                {r.total_marks}
              </td>
              <td className={`${cell} text-center font-semibold tabular-nums`}>
                {r.gpa.toFixed(2)}
              </td>
              <td
                className={`${cell} text-center font-semibold ${r.passed ? "" : "text-red-600 dark:text-red-400"}`}
              >
                {r.grade}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function Statistics({ stats }: { stats: ResultStatistics }) {
  const tiles = [
    { label: "Students", value: n(stats.students) },
    { label: "Passed", value: `${stats.passed}` },
    {
      label: "Pass rate",
      value: stats.pass_rate === null ? "—" : `${stats.pass_rate}%`,
    },
    {
      label: "Average GPA",
      value: stats.average_gpa === null ? "—" : stats.average_gpa.toFixed(2),
    },
  ];
  const most = Math.max(1, ...stats.grades.map((g) => g.students));

  return (
    <div className="space-y-5">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {tiles.map((t) => (
          <div key={t.label} className="card p-4">
            <p className="text-xs text-slate-500">{t.label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">
              {t.value}
            </p>
          </div>
        ))}
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <section className="card p-5">
          <h2 className="mb-3 text-sm font-semibold">Grades</h2>
          <ul className="space-y-2">
            {stats.grades.map((g) => (
              <li key={g.grade} className="flex items-center gap-3 text-sm">
                <span className="w-8 font-semibold">{g.grade}</span>
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div
                    className="bg-brand-500 h-full rounded-full"
                    style={{ width: `${(g.students / most) * 100}%` }}
                  />
                </div>
                <span className="w-8 text-right text-slate-500 tabular-nums">
                  {g.students}
                </span>
              </li>
            ))}
          </ul>
        </section>

        {stats.by_section.length > 1 && (
          <section className="card p-5">
            <h2 className="mb-3 text-sm font-semibold">By section</h2>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-slate-500">
                  <th className="pb-1 font-medium">Section</th>
                  <th className="pb-1 font-medium">Passed</th>
                  <th className="pb-1 font-medium">Pass rate</th>
                  <th className="pb-1 font-medium">Avg GPA</th>
                </tr>
              </thead>
              <tbody>
                {stats.by_section.map((s) => (
                  <tr key={s.section.id} className="tabular-nums">
                    <td className="py-1">{s.section.name}</td>
                    <td className="py-1">
                      {s.passed}/{s.students}
                    </td>
                    <td className="py-1">{s.pass_rate}%</td>
                    <td className="py-1">{s.average_gpa.toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        )}
      </div>

      <section className="card p-5">
        <h2 className="mb-3 text-sm font-semibold">By subject</h2>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-slate-500">
                <th className="pr-3 pb-1 font-medium">Subject</th>
                <th className="pr-3 pb-1 font-medium">Sat</th>
                <th className="pr-3 pb-1 font-medium">Absent</th>
                <th className="pr-3 pb-1 font-medium">Passed</th>
                <th className="pr-3 pb-1 font-medium">Highest</th>
                <th className="pb-1 font-medium">Average</th>
              </tr>
            </thead>
            <tbody>
              {stats.subjects.map((s) => (
                <tr key={s.exam_subject_id} className="tabular-nums">
                  <td className="py-1 pr-3 font-medium">{s.subject}</td>
                  <td className="py-1 pr-3">{s.students - s.absent}</td>
                  <td className="py-1 pr-3">{s.absent}</td>
                  <td className="py-1 pr-3">
                    {s.passed}/{s.students} ({s.pass_rate}%)
                  </td>
                  <td className="py-1 pr-3">
                    {n(s.highest)}/{s.full_marks}
                  </td>
                  <td className="py-1">{n(s.average)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
