"use client";

import { Printer } from "lucide-react";
import { useEffect, useState } from "react";
import { PageLoader } from "@/components/Loading";
import { errorMessage } from "@/lib/errors";
import * as resultService from "@/services/resultService";
import type { MyResults as MyResultsData } from "@/types/result";
import { PassBadge } from "./ClassResults";
import { ReportCardSheet } from "./ReportCardSheet";

/** Student and parent portals: published results with their report cards. */
export function MyResults() {
  const [data, setData] = useState<MyResultsData | null>(null);
  const [open, setOpen] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    resultService
      .getMyResults()
      .then((d) => {
        if (active) setData(d);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  if (!data) {
    return error ? (
      <div className="mx-auto max-w-4xl p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const parent = data.type === "parent";
  const shown = open
    ? data.students.flatMap((s) => s.results).find((r) => r.id === open)
    : undefined;

  if (shown) {
    return (
      <div className="mx-auto max-w-4xl p-6 print:p-0">
        <div className="mb-4 flex items-center gap-3 print:hidden">
          <button
            type="button"
            onClick={() => setOpen(null)}
            className="text-sm text-slate-500 hover:underline"
          >
            ← All results
          </button>
          <button
            type="button"
            onClick={() => window.print()}
            className="btn btn-secondary btn-sm ml-auto"
          >
            <Printer className="h-4 w-4" /> Print
          </button>
        </div>
        <div className="overflow-hidden rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 print:rounded-none print:shadow-none print:ring-0">
          <ReportCardSheet card={shown} />
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Results</h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Exam results appear here once the school publishes them.
      </p>

      {data.students.length === 0 ? (
        <p className="text-sm text-slate-500">
          Your login isn&apos;t linked to a {parent ? "guardian" : "student"}{" "}
          record yet.
        </p>
      ) : (
        <div className="space-y-6">
          {data.students.map((s) => (
            <section key={s.student.id}>
              {parent && (
                <h2 className="mb-2 text-lg font-semibold">{s.student.name}</h2>
              )}
              {s.results.length === 0 ? (
                <p className="text-sm text-slate-500">
                  No published results yet.
                </p>
              ) : (
                <ul className="space-y-3">
                  {s.results.map((r) => (
                    <li
                      key={r.id}
                      className="card flex flex-wrap items-center gap-4 p-4"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="font-semibold">{r.exam.name}</p>
                        <p className="text-xs text-slate-500">
                          {r.exam.academic_year} · {r.class.name}{" "}
                          {r.section.name}
                        </p>
                      </div>
                      <div className="text-center">
                        <p className="text-xs text-slate-500">GPA</p>
                        <p className="text-lg font-bold tabular-nums">
                          {r.gpa.toFixed(2)}
                        </p>
                      </div>
                      <div className="text-center">
                        <p className="text-xs text-slate-500">Grade</p>
                        <p className="text-lg font-bold">{r.grade}</p>
                      </div>
                      <div className="text-center">
                        <p className="text-xs text-slate-500">Position</p>
                        <p className="text-lg font-bold tabular-nums">
                          {r.position ?? "—"}
                        </p>
                      </div>
                      <PassBadge row={r} />
                      <button
                        type="button"
                        onClick={() => setOpen(r.id)}
                        className="btn btn-secondary btn-sm"
                      >
                        Report card
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
