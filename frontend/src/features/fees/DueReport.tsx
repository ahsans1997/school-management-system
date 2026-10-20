"use client";

import { Printer } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { PageLoader } from "@/components/Loading";
import { Pagination } from "@/components/Pagination";
import { errorMessage } from "@/lib/errors";
import { formatDay, formatMoney, formatPeriod, localToday } from "@/lib/format";
import * as feeService from "@/services/feeService";
import type { DueReport as Report, FeeOptions } from "@/types/fee";

const inputClass = "input input-sm";

interface Filters {
  page: number;
  academicYearId: number | null;
  classId: number | null;
  overdue: boolean;
}

/** Students with money outstanding, most owed first. */
export function DueReport() {
  const [options, setOptions] = useState<FeeOptions | null>(null);
  const [report, setReport] = useState<Report | null>(null);
  const [filters, setFilters] = useState<Filters>({
    page: 1,
    academicYearId: null,
    classId: null,
    overdue: false,
  });
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    Promise.all([feeService.getOptions(), feeService.getDueReport()])
      .then(([o, r]) => {
        if (!active) return;
        setOptions(o);
        setReport(r);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  const load = useCallback(async (next: Filters) => {
    setError(null);
    try {
      setReport(await feeService.getDueReport(next));
      setFilters(next);
    } catch (err) {
      setError(errorMessage(err));
    }
  }, []);

  if (!options || !report) {
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

  const today = localToday();
  const set = (patch: Partial<Filters>) =>
    void load({ ...filters, ...patch, page: 1 });

  return (
    <div className="mx-auto max-w-5xl p-6">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold">Due report</h1>
        <button
          type="button"
          onClick={() => window.print()}
          className="btn btn-secondary btn-sm print:hidden"
        >
          <Printer className="h-4 w-4" /> Print
        </button>
      </div>
      <p className="mb-5 text-sm text-slate-500 dark:text-slate-400">
        Unpaid and part-paid invoices, by student.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}

      <div className="mb-4 flex flex-wrap items-center gap-2 print:hidden">
        <select
          aria-label="Academic year"
          value={filters.academicYearId ?? ""}
          onChange={(e) =>
            set({
              academicYearId: e.target.value ? Number(e.target.value) : null,
            })
          }
          className={inputClass}
        >
          <option value="">All years</option>
          {options.academic_years.map((y) => (
            <option key={y.id} value={y.id}>
              {y.name}
            </option>
          ))}
        </select>
        <select
          aria-label="Class"
          value={filters.classId ?? ""}
          onChange={(e) =>
            set({ classId: e.target.value ? Number(e.target.value) : null })
          }
          className={inputClass}
        >
          <option value="">All classes</option>
          {options.classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={filters.overdue}
            onChange={(e) => set({ overdue: e.target.checked })}
          />
          Overdue only
        </label>
      </div>

      <div className="mb-4 flex flex-wrap gap-6 text-sm">
        <span>
          <span className="text-slate-500">Students owing </span>
          <strong className="tabular-nums">{report.totals.students}</strong>
        </span>
        <span>
          <span className="text-slate-500">Invoices </span>
          <strong className="tabular-nums">{report.totals.invoices}</strong>
        </span>
        <span>
          <span className="text-slate-500">Outstanding </span>
          <strong className="text-amber-700 tabular-nums dark:text-amber-400">
            {formatMoney(report.totals.due, report.currency)}
          </strong>
        </span>
      </div>

      {report.data.length === 0 ? (
        <p className="text-sm text-slate-500">Nothing outstanding.</p>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr className="table-head">
                <th className="py-2 pr-3 font-medium">Student</th>
                <th className="py-2 pr-3 font-medium">Class</th>
                <th className="py-2 pr-3 font-medium">Invoices</th>
                <th className="py-2 pr-3 font-medium">Oldest month</th>
                <th className="py-2 pr-3 font-medium">Earliest due</th>
                <th className="py-2 text-right font-medium">Due</th>
              </tr>
            </thead>
            <tbody>
              {report.data.map((r) => (
                <tr key={r.student.id} className="table-row">
                  <td className="py-2 pr-3">
                    <span className="font-medium">{r.student.name}</span>
                    <span className="block text-xs text-slate-500 tabular-nums">
                      {r.student.student_id}
                    </span>
                  </td>
                  <td className="py-2 pr-3 whitespace-nowrap">
                    {r.class} {r.section}
                    {r.roll !== null && (
                      <span className="block text-xs text-slate-500">
                        Roll {r.roll}
                      </span>
                    )}
                  </td>
                  <td className="py-2 pr-3 tabular-nums">{r.invoices}</td>
                  <td className="py-2 pr-3 whitespace-nowrap">
                    {formatPeriod(r.oldest_period)}
                  </td>
                  <td
                    className={`py-2 pr-3 whitespace-nowrap ${r.earliest_due < today ? "font-medium text-red-600 dark:text-red-400" : ""}`}
                  >
                    {formatDay(r.earliest_due)}
                  </td>
                  <td className="py-2 text-right font-semibold tabular-nums">
                    {formatMoney(r.due, report.currency)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pagination
        page={report.meta.current_page}
        lastPage={report.meta.last_page}
        onPage={(p) => void load({ ...filters, page: p })}
      />
    </div>
  );
}
