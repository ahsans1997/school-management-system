"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { saveBlob } from "@/lib/download";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import * as studentToolsService from "@/services/studentToolsService";
import {
  IMPORT_COLUMNS,
  type ImportResult,
  type ImportRowErrors,
  type SheetFormat,
} from "@/types/studentTools";

/** Row errors from a 422: keys "rows.N" → messages, in row order. */
function rowErrorsOf(err: unknown): ImportRowErrors[] {
  if (!(err instanceof ApiRequestError) || !err.error.errors) return [];
  return Object.entries(err.error.errors)
    .map(([key, errors]) => ({
      row: Number(/^rows\.(\d+)$/.exec(key)?.[1]),
      errors,
    }))
    .filter((r) => !Number.isNaN(r.row))
    .sort((a, b) => a.row - b.row);
}

/**
 * Import students from CSV or Excel: check the file (every row validated,
 * nothing saved), fix any rows reported, then import — all rows or none.
 */
export function StudentImport() {
  const [file, setFile] = useState<File | null>(null);
  const [checked, setChecked] = useState<ImportResult | null>(null);
  const [done, setDone] = useState<ImportResult | null>(null);
  const [rowErrors, setRowErrors] = useState<ImportRowErrors[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function chooseFile(next: File | null) {
    setFile(next);
    setChecked(null);
    setDone(null);
    setRowErrors([]);
    setError(null);
  }

  async function run(dryRun: boolean) {
    if (!file) return;
    setBusy(true);
    setError(null);
    setRowErrors([]);
    try {
      const result = await studentToolsService.importStudents(file, dryRun);
      if (dryRun) setChecked(result);
      else {
        setDone(result);
        setChecked(null);
      }
    } catch (err) {
      setChecked(null);
      setRowErrors(rowErrorsOf(err));
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function template(format: SheetFormat) {
    setError(null);
    try {
      saveBlob(
        await studentToolsService.downloadImportTemplate(format),
        `student-import-template.${format}`,
      );
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <Link
        href="/admin/students"
        className="text-sm text-gray-500 hover:underline"
      >
        ← Students
      </Link>
      <h1 className="mt-1 mb-1 text-2xl font-semibold">Import students</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Upload a CSV or Excel file with one student per row. Every row is
        checked first; if any row has a problem, nothing is imported. Rows with
        a class and section are enrolled for the current year.
      </p>

      <section className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
        <div className="mb-3 flex flex-wrap items-center gap-2 text-sm">
          <span className="font-semibold">Template:</span>
          <button
            type="button"
            onClick={() => void template("xlsx")}
            className="rounded-md border border-gray-300 px-3 py-1 dark:border-gray-700"
          >
            Excel (.xlsx)
          </button>
          <button
            type="button"
            onClick={() => void template("csv")}
            className="rounded-md border border-gray-300 px-3 py-1 dark:border-gray-700"
          >
            CSV
          </button>
          <span className="text-xs text-gray-500">
            An export from the Students page also works as an import file.
          </span>
        </div>
        <details className="text-sm">
          <summary className="cursor-pointer text-gray-600 dark:text-gray-400">
            Columns
          </summary>
          <table className="mt-2 text-xs">
            <tbody>
              {IMPORT_COLUMNS.map((c) => (
                <tr key={c.name}>
                  <td className="py-0.5 pr-4 font-medium">{c.name}</td>
                  <td className="py-0.5 text-gray-500">{c.note}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </details>
      </section>

      <form
        onSubmit={(e: FormEvent<HTMLFormElement>) => {
          e.preventDefault();
          void run(true);
        }}
        className="mb-6 flex flex-wrap items-center gap-3"
      >
        <input
          type="file"
          aria-label="Student file"
          accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          onChange={(e) => chooseFile(e.target.files?.[0] ?? null)}
          className="max-w-full text-sm"
        />
        <button
          type="submit"
          disabled={!file || busy}
          className="rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50 dark:border-gray-700"
        >
          {busy && !checked ? "Checking…" : "Check file"}
        </button>
        {checked && (
          <button
            type="button"
            disabled={busy}
            onClick={() => void run(false)}
            className="rounded-md bg-gray-900 px-4 py-1 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
          >
            {busy
              ? "Importing…"
              : `Import ${checked.rows} ${checked.rows === 1 ? "student" : "students"}`}
          </button>
        )}
      </form>

      {error && (
        <p role="alert" className="mb-3 text-sm text-red-600">
          {rowErrors.length > 0
            ? `${rowErrors.length} ${rowErrors.length === 1 ? "row has" : "rows have"} problems. Nothing was imported; fix them and check again.`
            : error}
        </p>
      )}

      {rowErrors.length > 0 && (
        <table className="mb-6 w-full text-sm">
          <thead>
            <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800">
              <th className="py-1 pr-3 font-medium">Row</th>
              <th className="py-1 font-medium">Problem</th>
            </tr>
          </thead>
          <tbody>
            {rowErrors.map((r) => (
              <tr
                key={r.row}
                className="border-b border-gray-100 align-top dark:border-gray-900"
              >
                <td className="py-1 pr-3 tabular-nums">{r.row}</td>
                <td className="py-1">
                  {r.errors.map((m) => (
                    <p key={m} className="text-red-600">
                      {m}
                    </p>
                  ))}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {checked && (
        <p className="mb-6 text-sm text-green-600">
          All {checked.rows} {checked.rows === 1 ? "row is" : "rows are"} ready
          to import
          {checked.enrolling > 0 &&
            `; ${checked.enrolling} will be enrolled in their class`}
          .
          {checked.ignored_columns.length > 0 && (
            <span className="block text-gray-500">
              Ignored columns: {checked.ignored_columns.join(", ")}.
            </span>
          )}
        </p>
      )}

      {done?.students && (
        <section>
          <p className="mb-3 text-sm text-green-600">
            Imported {done.students.length}{" "}
            {done.students.length === 1 ? "student" : "students"}
            {done.enrolling > 0 && `, ${done.enrolling} enrolled`}.
          </p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800">
                  <th className="py-1 pr-3 font-medium">Row</th>
                  <th className="py-1 pr-3 font-medium">Student</th>
                  <th className="py-1 pr-3 font-medium">Student ID</th>
                  <th className="py-1 pr-3 font-medium">Admission no.</th>
                  <th className="py-1 font-medium">Class</th>
                </tr>
              </thead>
              <tbody>
                {done.students.map((s) => (
                  <tr
                    key={s.id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="py-1 pr-3 tabular-nums">{s.row}</td>
                    <td className="py-1 pr-3">
                      <Link
                        href={`/admin/students/${s.id}`}
                        className="font-medium hover:underline"
                      >
                        {s.name}
                      </Link>
                    </td>
                    <td className="py-1 pr-3 tabular-nums">{s.student_id}</td>
                    <td className="py-1 pr-3 tabular-nums">{s.admission_no}</td>
                    <td className="py-1">
                      {s.class
                        ? `${s.class} ${s.section} · Roll ${s.roll}`
                        : "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  );
}
