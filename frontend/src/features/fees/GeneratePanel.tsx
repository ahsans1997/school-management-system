"use client";

import { useEffect, useState, type FormEvent } from "react";
import { errorMessage } from "@/lib/errors";
import { formatMoney, formatPeriod, localToday } from "@/lib/format";
import * as feeService from "@/services/feeService";
import type { FeeOptions, FeeStructure, InvoiceRun } from "@/types/fee";

const inputClass = "input input-sm";

/** Polls a run every 2 s while it's queued or running. */
const POLL_MS = 2000;

/**
 * Generate a month's invoices for a class or section. The work happens in
 * the background; this polls the run and reports what it created.
 */
export function GeneratePanel({
  options,
  onDone,
  onClose,
}: {
  options: FeeOptions;
  onDone: () => void;
  onClose: () => void;
}) {
  const current =
    options.academic_years.find((y) => y.is_current) ??
    options.academic_years[0];
  const [yearId, setYearId] = useState(String(current?.id ?? ""));
  const [classId, setClassId] = useState(String(options.classes[0]?.id ?? ""));
  const [sectionId, setSectionId] = useState("");
  const [period, setPeriod] = useState(localToday().slice(0, 7));
  const [dueDate, setDueDate] = useState("");
  const [choose, setChoose] = useState(false);
  const [structures, setStructures] = useState<FeeStructure[]>([]);
  const [chosen, setChosen] = useState<number[]>([]);
  const [run, setRun] = useState<InvoiceRun | null>(null);
  const [polls, setPolls] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const klass = options.classes.find((c) => String(c.id) === classId);

  // The class's fees, for choosing which to bill. State set only in callbacks.
  useEffect(() => {
    if (!yearId || !classId) return;
    let active = true;
    feeService
      .listStructures(Number(yearId), Number(classId))
      .then((list) => {
        if (!active) return;
        const live = list.filter((s) => s.is_active && s.fee_type?.is_active);
        setStructures(live);
        setChosen(
          live
            .filter((s) => s.frequency === "monthly")
            .map((s) => s.fee_type!.id),
        );
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [yearId, classId]);

  // Follow the run until it finishes.
  useEffect(() => {
    if (!run || run.status === "completed" || run.status === "failed") return;
    const timer = setTimeout(() => {
      feeService
        .getRun(run.id)
        .then((next) => {
          setRun(next);
          setPolls((p) => p + 1);
          if (next.status === "completed") onDone();
        })
        .catch((err) => setError(errorMessage(err)));
    }, POLL_MS);
    return () => clearTimeout(timer);
  }, [run, onDone]);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setPolls(0);
    try {
      const started = await feeService.generateInvoices({
        academic_year_id: yearId ? Number(yearId) : null,
        class_id: Number(classId),
        section_id: sectionId ? Number(sectionId) : null,
        period,
        due_date: dueDate || null,
        fee_type_ids: choose ? chosen : null,
      });
      setRun(started);
      if (started.status === "completed") onDone();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const working =
    run !== null && (run.status === "queued" || run.status === "running");

  return (
    <section className="card mb-6 p-5">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold">Generate invoices</h2>
        <button
          type="button"
          onClick={onClose}
          className="btn btn-ghost btn-sm"
        >
          Close
        </button>
      </div>
      <form onSubmit={(e) => void submit(e)} className="space-y-3">
        <div className="flex flex-wrap items-end gap-2">
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Year</span>
            <select
              value={yearId}
              onChange={(e) => setYearId(e.target.value)}
              className={inputClass}
            >
              {options.academic_years.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Class</span>
            <select
              value={classId}
              onChange={(e) => {
                setClassId(e.target.value);
                setSectionId("");
              }}
              className={inputClass}
            >
              {options.classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Section</span>
            <select
              value={sectionId}
              onChange={(e) => setSectionId(e.target.value)}
              className={inputClass}
            >
              <option value="">All sections</option>
              {klass?.sections.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Month</span>
            <input
              type="month"
              required
              value={period}
              onChange={(e) => setPeriod(e.target.value)}
              className={inputClass}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Due date</span>
            <input
              type="date"
              value={dueDate}
              onChange={(e) => setDueDate(e.target.value)}
              className={inputClass}
            />
          </label>
        </div>
        <p className="text-xs text-slate-500">
          Leave the due date blank for the end of the month. Students who
          already have an invoice for {formatPeriod(period)} are skipped.
        </p>

        <div className="text-sm">
          <label className="mr-4 inline-flex items-center gap-2">
            <input
              type="radio"
              checked={!choose}
              onChange={() => setChoose(false)}
            />
            Usual fees (monthly + once-a-year fees not billed yet)
          </label>
          <label className="inline-flex items-center gap-2">
            <input
              type="radio"
              checked={choose}
              onChange={() => setChoose(true)}
            />
            Choose fees (e.g. add an exam fee)
          </label>
        </div>
        {choose && (
          <div className="flex flex-wrap gap-3 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
            {structures.length === 0 && (
              <span className="text-slate-500">
                No active fees set for this class and year.
              </span>
            )}
            {structures.map((s) => (
              <label key={s.id} className="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={chosen.includes(s.fee_type!.id)}
                  onChange={(e) =>
                    setChosen((c) =>
                      e.target.checked
                        ? [...c, s.fee_type!.id]
                        : c.filter((id) => id !== s.fee_type!.id),
                    )
                  }
                />
                {s.fee_type?.name}{" "}
                <span className="text-slate-500 tabular-nums">
                  {formatMoney(s.amount, options.currency)}
                </span>
              </label>
            ))}
          </div>
        )}

        {error && (
          <p role="alert" className="alert alert-error">
            {error}
          </p>
        )}

        <button
          type="submit"
          disabled={busy || working || (choose && chosen.length === 0)}
          className="btn btn-primary"
        >
          {busy ? "Starting…" : "Generate"}
        </button>
      </form>

      {run && (
        <div
          role="status"
          className={`mt-4 rounded-lg border px-4 py-3 text-sm ${
            run.status === "failed"
              ? "border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
              : run.status === "completed"
                ? "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200"
                : "border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50"
          }`}
        >
          {run.status === "queued" && (
            <>
              Queued: {run.class?.name}
              {run.section ? ` ${run.section.name}` : ""},{" "}
              {formatPeriod(run.period)}…
              {polls >= 4 && (
                <span className="mt-1 block text-xs text-slate-500">
                  Still waiting? The queue worker must be running on the server
                  (<code>php artisan queue:work</code>).
                </span>
              )}
            </>
          )}
          {run.status === "running" && "Generating invoices…"}
          {run.status === "completed" && (
            <>
              Done: {run.created} {run.created === 1 ? "invoice" : "invoices"}{" "}
              created for {formatPeriod(run.period)} (
              {formatMoney(run.total, options.currency)})
              {run.skipped > 0 &&
                `, ${run.skipped} skipped (already invoiced or nothing to bill)`}
              .
            </>
          )}
          {run.status === "failed" && (
            <>Failed: {run.error ?? "unknown error"}</>
          )}
        </div>
      )}
    </section>
  );
}
