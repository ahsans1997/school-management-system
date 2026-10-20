"use client";

import { Lock, Send } from "lucide-react";
import Link from "next/link";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type KeyboardEvent,
} from "react";
import { PageLoader } from "@/components/Loading";
import { errorMessage } from "@/lib/errors";
import { formatDate, formatDay } from "@/lib/format";
import { labelOf } from "@/lib/options";
import { ApiRequestError } from "@/services/apiClient";
import * as marksService from "@/services/marksService";
import {
  MARK_PARTS,
  SHEET_STATUSES,
  type MarkPart,
  type MarkRecordInput,
  type MarkSheet,
} from "@/types/marks";
import { STATUS_BADGE } from "./MarksPage";

/** A row as typed: "" means blank. `marks` is for papers without a split. */
interface Row {
  absent: boolean;
  cq: string;
  mcq: string;
  practical: string;
  marks: string;
}

type Field = MarkPart | "marks";

const text = (v: number | null | undefined) =>
  v === null || v === undefined ? "" : String(v);
const num = (v: string) => (v.trim() === "" ? null : Number(v));
/** Sums to two decimals without float noise (25.5 + 12.25 = 37.75). */
const round2 = (v: number) => Math.round(v * 100) / 100;

function toRows(sheet: MarkSheet): Record<number, Row> {
  return Object.fromEntries(
    sheet.students.map((s) => [
      s.student_id,
      {
        absent: s.mark?.absent ?? false,
        cq: text(s.mark?.cq),
        mcq: text(s.mark?.mcq),
        practical: text(s.mark?.practical),
        // A paper without a split stores the figure as the total.
        marks: sheet.parts.length === 0 ? text(s.mark?.total) : "",
      },
    ]),
  );
}

/**
 * One sheet: a paper as sat by one section. Type marks per student (each
 * part capped, total worked out as you type), save as drafts, then submit
 * to lock them for results. Enter / ↑ / ↓ move between rows.
 */
export function MarkSheetEditor({
  examId,
  paperId,
  sectionId,
  basePath,
}: {
  examId: number;
  paperId: number;
  sectionId: number;
  basePath: string;
}) {
  const [sheet, setSheet] = useState<MarkSheet | null>(null);
  const [rows, setRows] = useState<Record<number, Row>>({});
  const [rowErrors, setRowErrors] = useState<Record<number, string[]>>({});
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState<"save" | "submit" | "reopen" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const grid = useRef<HTMLTableSectionElement>(null);

  const apply = useCallback((s: MarkSheet) => {
    setSheet(s);
    setRows(toRows(s));
    setRowErrors({});
    setDirty(false);
  }, []);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    marksService
      .getSheet(examId, paperId, sectionId)
      .then((s) => {
        if (active) apply(s);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [examId, paperId, sectionId, apply]);

  if (!sheet) {
    return error ? (
      <div className="mx-auto max-w-5xl p-6">
        <Link
          href={`${basePath}?exam=${examId}`}
          className="text-sm text-slate-500 hover:underline"
        >
          ← Marks entry
        </Link>
        <p role="alert" className="alert alert-error mt-4">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const paper = sheet.paper;
  const paperName = paper.subject?.name ?? "Paper";
  const editable = sheet.can_edit;
  const parts = MARK_PARTS.filter((p) => sheet.parts.includes(p.value));
  const fields: { field: Field; label: string; cap: number }[] =
    parts.length > 0
      ? parts.map((p) => ({
          field: p.value,
          label: p.label,
          cap: paper[p.cap] ?? 0,
        }))
      : [{ field: "marks", label: "Marks", cap: paper.full_marks }];

  function update(studentId: number, patch: Partial<Row>) {
    setRows((rs) => ({ ...rs, [studentId]: { ...rs[studentId], ...patch } }));
    setDirty(true);
  }

  /** The total as typed so far: null when nothing's entered. */
  function totalOf(row: Row): number | null {
    if (row.absent) return null;
    const values = fields.map((f) => num(row[f.field]));
    if (values.every((v) => v === null)) return null;
    return round2(values.reduce<number>((sum, v) => sum + (v ?? 0), 0));
  }

  const records = (): MarkRecordInput[] =>
    sheet.students.map((s) => {
      const row = rows[s.student_id];
      const value = (field: Field) =>
        row.absent || !fields.some((f) => f.field === field)
          ? null
          : num(row[field]);
      return {
        student_id: s.student_id,
        absent: row.absent,
        cq: value("cq"),
        mcq: value("mcq"),
        practical: value("practical"),
        marks: value("marks"),
      };
    });

  /** Pin "records.N.field" errors to row N. */
  function pinErrors(err: unknown) {
    if (!(err instanceof ApiRequestError) || !err.error.errors) return;
    const byRow: Record<number, string[]> = {};
    for (const [key, messages] of Object.entries(err.error.errors)) {
      const match = /^records\.(\d+)/.exec(key);
      if (match) {
        const index = Number(match[1]);
        byRow[index] = [...(byRow[index] ?? []), ...messages];
      }
    }
    setRowErrors(byRow);
  }

  async function run(
    kind: "save" | "submit" | "reopen",
    task: () => Promise<MarkSheet>,
    success: (s: MarkSheet) => string,
  ) {
    setBusy(kind);
    setError(null);
    setNotice(null);
    setRowErrors({});
    try {
      const next = await task();
      apply(next);
      setNotice(success(next));
    } catch (err) {
      setError(errorMessage(err));
      pinErrors(err);
    } finally {
      setBusy(null);
    }
  }

  const save = () =>
    run(
      "save",
      () => marksService.saveSheet(examId, paperId, sectionId, records()),
      (s) =>
        `Saved as draft: ${s.summary.entered} of ${s.summary.students} students have marks.`,
    );

  const submit = () => {
    if (
      !window.confirm(
        `Submit ${paperName} marks for ${sheet.section.class} ${sheet.section.name}? They'll be locked for results; only an exam admin can reopen them.`,
      )
    )
      return;
    void run(
      "submit",
      async () => {
        // Save what's on screen first, so nothing typed is left out.
        if (dirty) {
          await marksService.saveSheet(examId, paperId, sectionId, records());
        }
        return marksService.submitSheet(examId, paperId, sectionId);
      },
      () => "Marks submitted and locked for results.",
    );
  };

  const reopen = () =>
    run(
      "reopen",
      () => marksService.reopenSheet(examId, paperId, sectionId),
      () => "Reopened: the marks are drafts again and can be changed.",
    );

  /** Enter / ↓ / ↑ move to the same column in the next or previous row. */
  function onKeyDown(
    e: KeyboardEvent<HTMLInputElement>,
    index: number,
    col: number,
  ) {
    const step =
      e.key === "Enter" || e.key === "ArrowDown"
        ? 1
        : e.key === "ArrowUp"
          ? -1
          : 0;
    if (step === 0) return;
    e.preventDefault();
    grid.current
      ?.querySelector<HTMLInputElement>(`[data-cell="${index + step}:${col}"]`)
      ?.focus();
  }

  const entered = sheet.students.filter((s) => {
    const row = rows[s.student_id];
    return row && (row.absent || totalOf(row) !== null);
  }).length;

  return (
    <div className="mx-auto max-w-5xl p-6">
      <Link
        href={`${basePath}?exam=${examId}`}
        className="text-sm text-slate-500 hover:underline"
      >
        ← Marks entry
      </Link>
      <div className="mt-1 mb-1 flex flex-wrap items-center gap-3">
        <h1 className="text-2xl font-semibold">
          {paperName} · {sheet.section.class} {sheet.section.name}
        </h1>
        <span className={STATUS_BADGE[sheet.status]}>
          {labelOf(SHEET_STATUSES, sheet.status)}
        </span>
      </div>
      <p className="mb-5 text-sm text-slate-500 dark:text-slate-400">
        {sheet.exam.name} · Full marks {paper.full_marks}
        {parts.length > 0 &&
          ` (${fields.map((f) => `${f.label} ${f.cap}`).join(" + ")})`}{" "}
        · Pass marks {paper.pass_marks}
        {paper.exam_date && ` · Sat on ${formatDay(paper.exam_date)}`}
      </p>

      {!sheet.open && (
        <p className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
          Marks can be entered from {formatDay(sheet.opens_on)}, once the paper
          is sat.
        </p>
      )}
      {sheet.status === "submitted" && (
        <p className="mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
          <Lock className="mt-0.5 h-4 w-4 shrink-0" />
          <span>
            Submitted
            {sheet.submitted?.by ? ` by ${sheet.submitted.by}` : ""}
            {sheet.submitted?.at ? ` on ${formatDate(sheet.submitted.at)}` : ""}
            . The marks are locked for results.
            {!sheet.can_reopen &&
              " If something needs correcting, ask an exam admin to reopen them."}
          </span>
        </p>
      )}
      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success mb-4">{notice}</p>}

      {sheet.students.length === 0 ? (
        <p className="text-sm text-slate-500">
          No students are on this section&apos;s roll for the paper.
        </p>
      ) : (
        <>
          <div className="table-wrap">
            <table>
              <thead>
                <tr className="table-head">
                  <th className="py-2 pr-3 font-medium">Roll</th>
                  <th className="py-2 pr-3 font-medium">Student</th>
                  {fields.map((f) => (
                    <th key={f.field} className="py-2 pr-3 font-medium">
                      {f.label}{" "}
                      <span className="font-normal text-slate-400">
                        /{f.cap}
                      </span>
                    </th>
                  ))}
                  <th className="py-2 pr-3 font-medium">Total</th>
                  <th className="py-2 font-medium">Absent</th>
                </tr>
              </thead>
              <tbody ref={grid}>
                {sheet.students.map((s, i) => {
                  const row = rows[s.student_id];
                  if (!row) return null;
                  const total = totalOf(row);
                  return (
                    <tr key={s.student_id} className="table-row align-top">
                      <td className="py-2 pr-3 text-slate-500 tabular-nums">
                        {s.roll ?? "—"}
                      </td>
                      <td className="py-2 pr-3">
                        <span className="font-medium">{s.name}</span>
                        {s.roll === null && (
                          <span className="block text-xs text-slate-500">
                            No longer on the roll
                          </span>
                        )}
                        {rowErrors[i]?.map((m) => (
                          <span
                            key={m}
                            className="mt-1 block max-w-64 text-xs text-red-600 dark:text-red-400"
                          >
                            {m}
                          </span>
                        ))}
                      </td>
                      {fields.map((f, col) => {
                        const value = num(row[f.field]);
                        const over = value !== null && value > f.cap;
                        return (
                          <td key={f.field} className="py-2 pr-3">
                            <input
                              type="number"
                              inputMode="decimal"
                              aria-label={`${f.label} for ${s.name}`}
                              aria-invalid={over || undefined}
                              data-cell={`${i}:${col}`}
                              min={0}
                              max={f.cap}
                              step="0.01"
                              disabled={!editable || row.absent}
                              value={row.absent ? "" : row[f.field]}
                              placeholder={row.absent ? "—" : undefined}
                              onChange={(e) =>
                                update(s.student_id, {
                                  [f.field]: e.target.value,
                                })
                              }
                              onKeyDown={(e) => onKeyDown(e, i, col)}
                              className={`input input-sm w-20 tabular-nums ${over ? "border-red-500 focus:border-red-500 focus:ring-red-500/20" : ""}`}
                            />
                          </td>
                        );
                      })}
                      <td className="py-2 pr-3 font-semibold tabular-nums">
                        {row.absent ? (
                          <span className="badge badge-red">Absent</span>
                        ) : total === null ? (
                          <span className="text-slate-400">—</span>
                        ) : (
                          <span
                            className={
                              total < paper.pass_marks
                                ? "text-red-600 dark:text-red-400"
                                : "text-slate-800 dark:text-slate-100"
                            }
                          >
                            {total}
                          </span>
                        )}
                      </td>
                      <td className="py-2">
                        <input
                          type="checkbox"
                          aria-label={`${s.name} was absent`}
                          checked={row.absent}
                          disabled={!editable}
                          onChange={(e) =>
                            update(s.student_id, { absent: e.target.checked })
                          }
                          className="mt-2 h-4 w-4"
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="mt-4 flex flex-wrap items-center gap-3">
            {editable && (
              <>
                <button
                  type="button"
                  disabled={busy !== null}
                  onClick={() => void save()}
                  className="btn btn-secondary"
                >
                  {busy === "save" ? "Saving…" : "Save draft"}
                </button>
                <button
                  type="button"
                  disabled={busy !== null}
                  onClick={submit}
                  className="btn btn-primary"
                >
                  <Send className="h-4 w-4" />
                  {busy === "submit" ? "Submitting…" : "Save & submit"}
                </button>
              </>
            )}
            {sheet.can_reopen && (
              <button
                type="button"
                disabled={busy !== null}
                onClick={() => void reopen()}
                className="btn btn-secondary"
              >
                {busy === "reopen" ? "Reopening…" : "Reopen for changes"}
              </button>
            )}
            <span className="text-xs text-slate-500 tabular-nums">
              {entered}/{sheet.students.length} entered
              {dirty && editable && (
                <span className="ml-2 font-medium text-amber-600 dark:text-amber-400">
                  · Unsaved changes
                </span>
              )}
            </span>
          </div>
        </>
      )}
    </div>
  );
}
