"use client";

import { useEffect, useState } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import * as examService from "@/services/examService";
import { STANDARD_GRADES, type GradeScale } from "@/types/exam";

const inputClass =
  "rounded-md border border-gray-300 px-1.5 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

interface Row {
  key: number;
  id?: number;
  name: string;
  grade_point: string;
  min_percent: string;
}

let nextKey = 1;

function toRows(
  grades: {
    id?: number;
    name: string;
    grade_point: number;
    min_percent: number;
  }[],
): Row[] {
  return grades.map((g) => ({
    key: nextKey++,
    id: g.id,
    name: g.name,
    grade_point: String(g.grade_point),
    min_percent: String(g.min_percent),
  }));
}

/** "80–100 %", "70 to under 80 %" — the range each band covers, top band first. */
function ranges(rows: Row[]): Map<number, string> {
  const sorted = rows
    .filter((r) => r.min_percent.trim() !== "")
    .sort((a, b) => Number(b.min_percent) - Number(a.min_percent));
  const out = new Map<number, string>();
  sorted.forEach((r, i) => {
    const from = Number(r.min_percent);
    out.set(
      r.key,
      i === 0
        ? `${from}–100 %`
        : `${from} to under ${Number(sorted[i - 1].min_percent)} %`,
    );
  });
  return out;
}

/**
 * Grade scales: bands are cut-offs, each running up to the next, so they
 * can't overlap or leave gaps. Editing needs exam.grading (it regrades
 * every result that uses the scale).
 */
export function GradeScalesManager() {
  const { can } = useAuth();
  const canEdit = can("exam.grading");
  const [scales, setScales] = useState<GradeScale[]>([]);
  const [selected, setSelected] = useState<number | "new" | null>(null);
  const [name, setName] = useState("");
  const [rows, setRows] = useState<Row[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  function open(scale: GradeScale | null, list = scales) {
    setErrors({});
    setError(null);
    if (scale === null) {
      setSelected("new");
      setName(list.length === 0 ? "GPA 5.00" : "");
      setRows(toRows(STANDARD_GRADES));
    } else {
      setSelected(scale.id);
      setName(scale.name);
      setRows(toRows(scale.grades));
    }
  }

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    examService
      .listGradeScales()
      .then((list) => {
        if (!active) return;
        setScales(list);
        if (list.length > 0) {
          setSelected(list[0].id);
          setName(list[0].name);
          setRows(toRows(list[0].grades));
        }
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
  }, []);

  function update(key: number, patch: Partial<Row>) {
    setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...patch } : r)));
  }

  async function save() {
    setBusy(true);
    setError(null);
    setNotice(null);
    setErrors({});
    const input = {
      name: name.trim(),
      grades: rows.map((r) => ({
        ...(r.id ? { id: r.id } : {}),
        name: r.name.trim(),
        grade_point: Number(r.grade_point),
        min_percent: Number(r.min_percent),
      })),
    };
    try {
      const saved =
        selected === "new" || selected === null
          ? await examService.createGradeScale(input)
          : await examService.updateGradeScale(selected, input);
      const list = await examService.listGradeScales();
      setScales(list);
      open(saved, list);
      setNotice(`${saved.name} saved.`);
    } catch (err) {
      setError(errorMessage(err));
      if (err instanceof ApiRequestError && err.error.errors) {
        setErrors(
          Object.fromEntries(
            Object.entries(err.error.errors).map(([k, v]) => [k, v[0]]),
          ),
        );
      }
    } finally {
      setBusy(false);
    }
  }

  async function remove(scale: GradeScale) {
    if (!window.confirm(`Delete ${scale.name}? This cannot be undone.`)) return;
    setError(null);
    setNotice(null);
    try {
      await examService.deleteGradeScale(scale.id);
      const list = scales.filter((s) => s.id !== scale.id);
      setScales(list);
      if (list.length > 0) open(list[0], list);
      else {
        setSelected(null);
        setRows([]);
      }
      setNotice(`${scale.name} deleted.`);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  const current =
    typeof selected === "number"
      ? scales.find((s) => s.id === selected)
      : undefined;
  const rangeOf = ranges(rows);

  return (
    <div className="mx-auto max-w-4xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Grade scales</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Each band starts at a percentage of the full marks and runs up to the
        next band, so every mark gets exactly one grade. One band must start at
        0 %. Each exam picks the scale its results use.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      <div className="mb-4 flex flex-wrap gap-2">
        {scales.map((s) => (
          <button
            key={s.id}
            type="button"
            onClick={() => open(s)}
            aria-pressed={selected === s.id}
            className={`rounded-md border px-3 py-1 text-sm ${
              selected === s.id
                ? "border-gray-900 bg-gray-900 text-white dark:border-gray-100 dark:bg-gray-100 dark:text-gray-900"
                : "border-gray-300 dark:border-gray-700"
            }`}
          >
            {s.name}
          </button>
        ))}
        {canEdit && (
          <button
            type="button"
            onClick={() => open(null)}
            aria-pressed={selected === "new"}
            className="rounded-md border border-dashed border-gray-400 px-3 py-1 text-sm dark:border-gray-600"
          >
            New scale
          </button>
        )}
      </div>

      {selected === null ? (
        <p className="text-sm text-gray-500">
          No grade scales yet{canEdit ? ": start one with “New scale”." : "."}
        </p>
      ) : (
        <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
          {selected === "new" && (
            <p className="mb-3 text-xs text-gray-500">
              Starts from the standard GPA 5.00 bands. Change them as needed.
            </p>
          )}
          {current && current.exams_count ? (
            <p className="mb-3 text-xs text-gray-500">
              Used by {current.exams_count}{" "}
              {current.exams_count === 1 ? "exam" : "exams"}.
            </p>
          ) : null}

          <label className="mb-4 block text-sm">
            <span className="mb-1 block font-medium">Name</span>
            <input
              maxLength={100}
              disabled={!canEdit}
              value={name}
              onChange={(e) => setName(e.target.value)}
              className={`w-full max-w-xs ${inputClass}`}
            />
            {errors.name && (
              <span className="mt-1 block text-xs text-red-600">
                {errors.name}
              </span>
            )}
          </label>

          <table className="w-full max-w-xl text-sm">
            <thead>
              <tr className="text-left text-xs text-gray-500">
                <th className="pr-2 pb-1 font-medium">Grade</th>
                <th className="pr-2 pb-1 font-medium">Grade point</th>
                <th className="pr-2 pb-1 font-medium">From (%)</th>
                <th className="pr-2 pb-1 font-medium">Covers</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => {
                const rowErrors = [
                  errors[`grades.${i}.name`],
                  errors[`grades.${i}.grade_point`],
                  errors[`grades.${i}.min_percent`],
                  errors[`grades.${i}.id`],
                ].filter(Boolean);
                return (
                  <tr key={r.key} className="align-top">
                    <td className="py-1 pr-2">
                      <input
                        aria-label="Grade"
                        maxLength={10}
                        disabled={!canEdit}
                        value={r.name}
                        onChange={(e) =>
                          update(r.key, { name: e.target.value })
                        }
                        className={`w-16 ${inputClass}`}
                      />
                      {rowErrors.map((m) => (
                        <p key={m} className="mt-1 w-72 text-xs text-red-600">
                          {m}
                        </p>
                      ))}
                    </td>
                    <td className="py-1 pr-2">
                      <input
                        type="number"
                        aria-label="Grade point"
                        min={0}
                        max={10}
                        step="0.01"
                        disabled={!canEdit}
                        value={r.grade_point}
                        onChange={(e) =>
                          update(r.key, { grade_point: e.target.value })
                        }
                        className={`w-20 ${inputClass}`}
                      />
                    </td>
                    <td className="py-1 pr-2">
                      <input
                        type="number"
                        aria-label="Starts at percent"
                        min={0}
                        max={100}
                        step="0.01"
                        disabled={!canEdit}
                        value={r.min_percent}
                        onChange={(e) =>
                          update(r.key, { min_percent: e.target.value })
                        }
                        className={`w-20 ${inputClass}`}
                      />
                    </td>
                    <td className="py-1 pr-2 whitespace-nowrap text-gray-500 tabular-nums">
                      {rangeOf.get(r.key) ?? "—"}
                    </td>
                    <td className="py-1">
                      {canEdit && (
                        <button
                          type="button"
                          aria-label="Remove band"
                          onClick={() =>
                            setRows((rs) => rs.filter((x) => x.key !== r.key))
                          }
                          className="px-1 text-red-600"
                        >
                          ×
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          {errors.grades && (
            <p className="mt-2 text-xs text-red-600">{errors.grades}</p>
          )}

          {canEdit && (
            <div className="mt-4 flex flex-wrap gap-3">
              <button
                type="button"
                onClick={() =>
                  setRows((rs) => [
                    ...rs,
                    {
                      key: nextKey++,
                      name: "",
                      grade_point: "",
                      min_percent: "",
                    },
                  ])
                }
                className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
              >
                Add band
              </button>
              <button
                type="button"
                disabled={busy}
                onClick={() => void save()}
                className="rounded-md bg-gray-900 px-4 py-1 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
              >
                {busy ? "Saving…" : "Save scale"}
              </button>
              {current && !current.exams_count && (
                <button
                  type="button"
                  onClick={() => void remove(current)}
                  className="ml-auto rounded-md border border-red-300 px-3 py-1 text-sm text-red-700 dark:border-red-800 dark:text-red-400"
                >
                  Delete scale
                </button>
              )}
            </div>
          )}
        </section>
      )}
    </div>
  );
}
